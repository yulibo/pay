<?php
/**
 优惠券模块
 */
namespace Common\Module;
use Common\Model\PromotionInfoModel;
use Common\Model\MemberMarketingDiscountModel;
use Common\Model\OrderLineModel;



use \Exception;

class MarketingDiscount  extends Module
{
	protected static $ins;//ins
	
	const ADD_USER_FLAG = 'ADDUSER';//新注册用户
	const RECHARGE_FLAG = 'RECHARGE';//充值赠送优惠券
	
	const REGISTER_TRI = 2;//注册触发条件
	const RECHARGE_TRI = 1;//充值触发添加
	
	protected function init(){
		
	}
	
	//赠送优惠券
	public function giveMarket($type){
		try{
			if(empty($type)){
				throw new Exception('请输入赠送优惠券类型');
			}
			if($type==self::ADD_USER_FLAG){
				return $this->giveAddUserMarket();//新用户注册添加优惠券
			}elseif($type==self::RECHARGE_FLAG){
				return $this->giveRechargeMarket();//充值赠送优惠券
			}
		}catch(Exception $e){
			file_put_contents('giveMarketError.txt',$e->getMessage(),8);
			$this->error = $e->getMessage();
			$this->errorCode = $e->getCode();
			return false;
		}
	}
	
	
	//根据订单金额获取可用的优惠券
	public function getMarketDisByMoney($money){
		return $this->getMemberMarketingDiscountModel()->getMarketDisByMoney(['member_id'=>$this->member_id,'reach_num'=>['elt',$money]]);
	}
	
	//添加用户赠送优惠券
	private function giveAddUserMarket(){
		$data = $this->getPromotionInfoAll(self::REGISTER_TRI); //注册优惠券信息
		//未找到 当前是否范围的 优惠券
		if(empty($data)){
			return false;
		}
		return $this->giveMarketingDis($this->member_id,$data);
	}

	//充值赠送优惠券
	private function giveRechargeMarket(){
		$data = $this->getPromotionInfoAll(self::RECHARGE_TRI); //充值触发添加
		//未找到 当前是否范围的 优惠券
		if(empty($data)){
			return false;
		}
		return $this->giveMarketingDis($this->member_id,$data);
	}
	
	//获取优惠券列表
	private function giveMarketingDis($memberId,$idList){
		if(empty($idList)){
			throw new Exception('促销ID不能为空');
		}
		if(empty($memberId)){
			throw new Exception('赠送用户ID不能为空');
		}
		return $this->getPromotionInfoModel()->giveMarketingDis($memberId,$idList);
	}
	
	//获取促销信息
	private function getPromotionInfoAll($trigger){
		if(empty($trigger)){
			throw new Exception('获取优惠券信息触发方式不能为空');
		}
		$time = time();
		$where=[];
		$where['is_trigger'] = $trigger;
		$where['status'] = 1;
		$where['del_flag'] = 0;
		$where['start_time'] = ['elt',$time];
		$where['end_time'] = ['egt',$time];
		//如果是充值
		if($trigger==self::RECHARGE_TRI){
			$where['city_code'] = $this->getPromotionInfoModel()->getUserCityCode($this->member_id);
		}
		$list = $this->getPromotionInfoModel()->getAll($where,'id');
		if(empty($list)){
			return false;
		}
		$idList = [];
		foreach($list as $val){
			$idList[] = $val['id'];
		}
		return $idList;
	}
		
	//获取用户优惠券model
	private function getMemberMarketingDiscountModel(){
		return new MemberMarketingDiscountModel();
	}
	 
	
	//修改优惠券状态为已使用
	public function updateMarketStatusByOrder($orderSn){
		if(empty($orderSn)){
			throw new Exception("订单编号不能为空", 300); 
		}
		$order = $this->getOrderInfoModel()->getOrderInfoRow(['order_sn'=>$orderSn]);
		if(empty($order['market_id'])){
			return true;
		}
		$m = M();
		$m->startTrans();
		$row = $this->getMemberMarketingDiscountModel()->table('dev_ywy.vm_member_marketing_discount')->lock(true)->where(['id'=>$order['market_id'],'member_id'=>$order['member_id']])->find();
		if($order['item_money']<$row['reach_num']){
			$m->rollback();
			throw new Exception("该优惠券不能被使用", 300); 
		}
		if(empty($row) || $row['status']==1){
			$m->rollback();
			throw new Exception("优惠券信息不存在", 300); 
		}
		$bool = $this->getMemberMarketingDiscountModel()->table('dev_ywy.vm_member_marketing_discount')->update(['id'=>$order['market_id']],['status'=>1,'modify_time'=>time()]);
		if(empty($bool)){
			$m->rollback();
			throw new Exception("修改优惠券状态信息失败", 300); 
		}
		$m->commit();
		return true;
	}
	
	//修改优惠券状态为可使用
	public function updateMarketStatusIsUsed($where){
		if(empty($where['order_sn'])|| empty($where['id'])){
			throw new Exception("修改优惠券状态参数错误"); 
		}
		$order = $this->getOrderInfoModel()->getOrderInfoRow(['member_id'=>$this->member_id,'order_sn'=>$where['order_sn']]);
		if($order['pay_status']==2){
			throw new Exception("订单已经支付成功,不能修改了"); 
		}
		if(empty($order['market_id']) || $order['order_sn']!=$where['order_sn']|| $order['market_id']!=$where['id']){
			throw new Exception("修改错误"); 
		}
		return $this->getMemberMarketingDiscountModel()->table('dev_ywy.vm_member_marketing_discount')->update(['id'=>$where['id'],'member_id'=>$this->member_id],['status'=>0,'modify_time'=>0]);
	}
	
	//获取order_info model
	private function getOrderInfoModel(){
		return new OrderLineModel();
	}
	
	//获取促销信息model
	private function getPromotionInfoModel(){
		return new PromotionInfoModel();
	}
	 
}
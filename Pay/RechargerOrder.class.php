<?php

/**
 * Introduction: 充值订单
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */
namespace Common\Module\Pay;


use Common\Model\MemberBalanceModel;
use \Exception;
use Common\Module\MarketingDiscount;
use Common\Model\PromotionInfoModel;

class RechargerOrder extends OrderOpt
{	
	
	/**
     * @var array 充值金额列表  => 赠送金额
     */
	public static $rechargerMoneyList =[
	/*
		50=>5,
		100=>15,
		200=>40,
		500=>120
		*/
	];
	
	

	
	 /**
     * 生成订单
	 * @param float $money  充值金额
	 * @throws Exception
	 * @return bool
     */
	public function addOrder($money=0){
		try{
			if(empty($this->payMethod)){
				throw new Exception('充值方式不能为空');
			}
			if(empty($money)){
				throw new Exception('充值订单金额不能为空');
			}
			if(empty($this->member_id)){
				throw new Exception('用户ID不能为空');
			}
			$order = [];
			$order['member_id'] = $this->member_id;//用户ID
			$order['money'] = $money;//用户金额
			$order['order_sn'] = $this->getOrderSn();//订单sn 
			$order['gift_amount'] = $this->getGiftAmount($money);//获取赠送金额
			$order['type'] = $this->payMethod;
			$order['name'] = '充值订单';
			$order['desc'] = $this->getRechagerDesc($money,$order['gift_amount']);//获取充值描述
			$order['create_time'] = time();
			$bool = $this->getMemberBalanceModel()->addOrder($order);
			if(empty($bool)){
				throw new Exception('添加订单失败');
			}
			$this->orderSn = $order['order_sn'];//订单编号
			$this->orderCntMoney = $money;//订单总金额
			$this->orderName = $order['name'];//支付订单名称
			return $this->createPayOrder();//创建支付订单
		}catch(Exception $e){
			$this->error = $e->getMessage();
			return false;
		}
	}
	
	
	 /**
     * 获取充值金额列表
     *
     * @return array $list
     */
	public function getRechagerMoneyList(){
		$this->getRechargerMoneyList();
		$list = self::$rechargerMoneyList;//充值金额列表
		$result = [];
		foreach($list as $key=>$val){
			$cval = [];
			$cval['money'] = $key;
			$cval['desc'] = $this->getRechagerDesc($key,$val);
			$result[] = $cval;
		}
		return $result;
	}
	
	 /**
     * 获取充值描述
     * @param float $money  充值金额
	 * @param float $gift   赠送金额
     * @return string $str  获取充值描述
     */
	private function getRechagerDesc($money,$gift){
		if(empty($money)){
			return false;
		}
		if(empty($gift)){
			return vsprintf('(充值%d元)', array($money)); 
		}
		return  vsprintf('(充%d送%d元)', array($money,$gift)); 
	}
	
	
	 /**
     * 支付回调订单
     *
	 * @throws Exception
	 * @return bool
     */
	public function callBack(){
		try{
			$this->getCallBackData();//设置回调数据
			$orderSn = $this->getOutTradeNo();//订单号
			if(empty($orderSn)){
				throw new Exception('订单编号不能为空');
			}
			$orderStatus = $this->getCallBackStatus();//支付状态
			$bool = $this->getMemberBalanceModel()->accountCallback($orderSn,$orderStatus);
			if(empty($bool)){
				throw new Exception('回调失败');
			}
			$this->callBackSuccessOpt();//支付回调成功需要做的操作
			return $bool;
		}catch(Exception $e){
			$this->error = $e->getMessage();
			return false;
		}
	}
	

	/**
     * 获取充值订单信息
     *
	 * @return array $row 
     */
	private function getRechagerLogRow(){
		return $this->getMemberBalanceModel()->getRechargeLogRow(['order_sn'=>$this->getOutTradeNo()],'member_id');
	}
	
	
	
	/**
     * 获取充值module
     *
	 * @return Common\Module\MarketingDiscount
     */
	private function getMarketingDiscountModule(){
		$row = $this->getRechagerLogRow();
		$module = MarketingDiscount::getIns();
		$module->member_id = $row['member_id'];
		return $module;
	}
	
	 /**
     * 支付回调成功需要做的操作
     *
     * @return void
     */
	public function callBackSuccessOpt(){
		$this->getMarketingDiscountModule()->giveMarket('RECHARGE');//充值成功送优惠券
		$this->payMethodModule->echoCallBackMsg();//输出回调信息
	}
	
		
	 /**
     * 创建支付订单
     *
     * @return mixd 
     */
	public function createPayOrder(){
		$this->payMethodModule->orderSn = $this->orderSn;//订单编号
		$this->payMethodModule->orderCntMoney = $this->orderCntMoney;//订单总金额
		$this->payMethodModule->orderName = $this->orderName;//支付订单名称
		$this->payMethodModule->notifyUrl = C('RECHARGE_CALLBACK_URL');//订单回调地址
		return $this->payMethodModule->createPayOrderOpt();
	}
	
	
	 /**
     * 获取赠送金额
     * @param float $money  充值金额
     * @return float $money
     */
	private function getGiftAmount($money=0){
		$this->getRechargerMoneyList();
		$gmoney = 0;//赠送金额
		foreach(self::$rechargerMoneyList as $key=>$val){
			if($money>=$key){
				$gmoney = $val;
			}
		}
		return $gmoney;
	}
	
	 /**
     * 获取充值列表
     * 
     * @return array $list
     */
	private function getRechargerMoneyList(){
		$list = $this->getPromotionInfoModel()->getRechargeList();
		if(empty($list)){
			return false;
		}
		foreach($list as $val){
			self::$rechargerMoneyList[$val['reach_num']] = $val['gift_num'];
		}
		return self::$rechargerMoneyList;
	}


	/**
     * 获取优惠信息model
     * 
     * @return Common\Model\PromotionInfoModel
     */
	private function getPromotionInfoModel(){
		return new PromotionInfoModel();
	}
	
	/**
     * 获取用户余额model
     * 
     * @return Common\Model\MemberBalanceModel
     */
	private function getMemberBalanceModel(){
		return new MemberBalanceModel();
	}
}
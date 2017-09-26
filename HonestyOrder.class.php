<?php
/**

 */
namespace Common\Module;
use Common\Model\MemberBalanceModel;
use Common\Model\PayPasswordModel;
use Common\Model\MemberCredOrderModel;

use \Exception;

class HonestyOrder  extends Module
{
	protected static $ins;//ins
	
	protected function init(){
		
	}
	
	//获取货架信息
	public function getShelfInfo($shelfCode){
		return $this->getMemberCredOrderModel()->getShelfInfo($shelfCode,$this->member_id);
	}
	
	//会调订单处理
	public function proc_result($data){
		return $this->getMemberCredOrderModel()->update(['order_status'=>1,'modified'=>time(),'plat_trade_no'=>$data['transaction_id'],'mch_id'=>$data['mch_id']],['order_sn'=>$data['out_trade_no']]); 
	}
	
	//添加订单
	public function addOrder($data){
		try{
			if(empty($data)){
				throw new Exception('数据不能为空');
			}
			return $this->getMemberCredOrderModel()->insert($data);
		}catch(Exception $e){
			throw new Exception('添加订单失败,请重试');
		}
	}
	
	//获取程序补单model 
	private function getMemberCredOrderModel(){
		return new MemberCredOrderModel();
	}
	 
	 //获取用户手机号
	 private function getMobile(){
		 return $this->getMemberModel()->getMobile($this->member_id);
	 }
	 
}
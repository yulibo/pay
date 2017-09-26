<?php
/**
 * Introduction: 余额支付
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */


namespace Common\Module\Pay\PayWay;

use Common\Model\MemberBalanceModel;
use Common\Model\PayPasswordModel;
use \Exception;
use Common\Module\Member;

class BalancePayOrder extends PayOrder
{
   
    public $memberId;//用户ID
	public $payPassword;//支付密码
	
	public $reduceDesc = '';//扣减余额描述
	public $callBackOptFun;//扣款成功需要做操作
	public $pay_type = 1;//消费类型
	
	public function __construct(){
		$this->payPassword = trim(I('param.pay_password'));//支付密码
	}
	
	
	//支付成功订单处理过程
	public function procResult(){
		return call_user_func_array($this->callBackOptFun,[$this->getCallBackData()]);
	}
	
   
	//创建支付订单
	protected function createPayOrder(){
		$this->reduceMemberbalance();//扣减用户余额
		//修改订单状态
		$bool = $this->procResult();
		if(empty($bool)){
			throw new Exception('处理订单状态失败');
		}
	}
	
	
	//扣减用户余额
	private function reduceMemberbalance(){
		//消费类型
		$data['pay_type'] = $this->pay_type;
		//订单编号
		$data['order_sn'] = $this->orderSn;
		//支付订单扣减
		return $this->getMemberBalanceModel()->reduceMemberbalance($this->memberId,$this->orderCntMoney,$this->reduceDesc,$data);
	}
	
	
	
	
	//设置支付数据
	public function setOrderData(){
		if(empty($this->orderName)){
			throw new Exception('订单名称不能为空');
		}
		if(empty($this->orderSn)){
			throw new Exception('订单编号不能为空');
		}
		if(empty($this->orderCntMoney) || $this->orderCntMoney<self::MIN_PAY_MONEY || !is_numeric($this->orderCntMoney)){
			throw new Exception('订单金额错误');
		}
		if(empty($this->memberId)){
			throw new Exception('用户ID不能为空');
		}
		$this->checkPayPassWord();//检查支付密码
		$orderData = [];
		$orderData['buyer_name'] = $this->orderName;
        $orderData['order_id'] = $this->orderSn;
        $orderData['order_amount'] = $this->orderCntMoney;
		$orderData['member_id'] = $this->memberId;
		return $this->orderData=$orderData;
	}
	
	
	
	//返回订单提交成功的信息
	public function getPayInfo(){
		return ['orderSn'=>$this->orderSn];
	}
	
	
	//检查用户余额是否足
	private function checkBalance(){
		return $this->getMemberBalanceModel()->checkAccountBalance($this->memberId,$this->orderCntMoney);
	}
	
	
	//获取支付返回数据
	protected function getCallBackData(){
		$result = [];
		$result['out_trade_no'] = $this->orderSn; ////订单编号
		$result['transaction_id'] =  $this->getTransactionId();////事务ID
		$result['mch_id'] =  $this->memberId;//用户ID
		return $result;
	}

	
		
	//返回状态码
	protected function getCallBackStatus(){
		
	}
	
	
	//回调数据返回订单编号
	protected function getOutTradeNo(){
		
	}
	
	//获取membermodule
	private function getMemberModule(){
		$module =  Member::getIns();//
		$module->member_id = $this->memberId;
		return $module;
	}
	
	//检查支付密码
	private function checkPayPassWord(){
		$data = $this->getMemberModule()->checkFreePayPassword($this->orderCntMoney);
		$row = $data['data'];//返回支付密码数据
		$str = md5($this->payPassword.$row['pwd_encry_salt']);//支付密码加密
		if(!$data['freePay'] && $str!=$row['pay_password']){
			throw new Exception('支付密码错误');
		}
		$this->checkBalance();//检查用户余额是否足
	}
	
		
	//获取事务提交ID
	private function getTransactionId(){
		 return   '400'.date('YmdHis') . rand(10000000,99999999);
	}
	
	//输出会调信息
	public function echoCallBackMsg(){
		
	}

	
	//获取账户用户model
	private function getMemberBalanceModel(){
		return new MemberBalanceModel();
	}
	
	//获取用户支付密码model
	private function getPayPasswordModel(){
		return new PayPasswordModel();
	}
}
<?php
/**
 * Introduction: 天翼支付
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */

namespace Common\Module\Pay\PayWay;
use Think\TyPay\AES;
use \Exception;

class TianyiPayOrder extends PayOrder
{

		//创建支付订单
	protected function createPayOrder(){
		
	}
	
	
	
	//设置支付数据
	public function setOrderData(){
		if(empty($this->orderName)){
			throw new Exception('订单名称不能为空');
		}
		if(empty($this->orderSn)){
			throw new Exception('订单编号不能为空');
		}
		if(empty($this->orderCntMoney)){
			throw new Exception('订单金额不能为空');
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
		
	}
	
	
	//获取支付返回数据
	protected function getCallBackData(){
		
	}

	
		
	//返回状态码
	protected function getCallBackStatus(){
		
	}
	
	
	//回调数据返回订单编号
	protected function getOutTradeNo(){
		
	}

}
<?php
/**
 * Introduction: 支付模块基类
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */

namespace Common\Module\Pay\PayWay;


abstract class PayOrder
{
    public $orderSn;//订单编号
	
	public $orderCntMoney;//订单总金额
	
	public $orderName;//支付订单名称
	
	public $orderDesc;//订单简介
	
	public $notifyUrl;//订单回调地址
	
	public $payOrderInfo;//支付订单返回信息
	
	protected $orderData;//支付订单提交数据
	
	protected $callBackData;//支付回调返回数据
	
	const MIN_PAY_MONEY = 0.01;//最小支付金额 单位元
	
	public function init(){
		$this->setOrderData();//设置支付提交数据
	}

	//获取支付信息
	public function getPayInfo(){
		return $this->payOrderInfo;
	}	
	
	//生成支付订单
	public function createPayOrderOpt(){
		$this->setOrderData(); //设置支付数据
		return $this->createPayOrder(); //生成支付订单
	}
	
	//输出会调信息
	abstract public function echoCallBackMsg();
	
	
	//生成支付订单
	abstract protected function createPayOrder();
	
	
	//设置支付数据
	abstract protected function setOrderData();

	
	//获取支付返回数据
	abstract protected function getCallBackData();
		
		
	//返回状态码
	abstract protected function getCallBackStatus();
	
	
	//回调数据返回订单编号
	abstract protected function getOutTradeNo();
		
	
}
<?php

/**
 * Introduction: 充值订单
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */
namespace Common\Module\Pay;
use Common\Module\Pay\PayWay\WePayOrder;
use Common\Module\Pay\PayWay\TianyiPayOrder;
use Common\Module\Pay\PayWay\AliPayOrder;
use Common\Module\Pay\PayWay\PayOrder;

abstract class OrderOpt
{
	/**
     * @var string 订单编号
     */
	public $orderSn;
	
	/**
     * @var string 订单总金额
     */
	public $orderCntMoney;
	
	/**
     * @var string 支付订单名称
     */
	public $orderName;
	
	/**
     * @var string 订单简介
     */
	public $orderDesc;
	
	/**
     * @var int 用户ID
     */
	public $member_id;
	
	/**
     * @var string 错误信息
     */
	public $error;
	
	/**
     * @var int 错误码
     */
	public $errorCode;
	
	/**
     * @var int 回调成功返回码
     */
	public $callbackStatus;
	
	
	/**
     * @var int 支付方式
     */
	protected $payMethod;
	
	/**
     * @var module 支付方式  module
     */
	protected  $payMethodModule;
	
	/**
     * @var this 当前对象
     */
	private static $ins;//ins
	
	/**
     * @var array 支付方式 1 微信,2 天翼, 3支付宝 ,4余额
     */
	protected static $payMethodList = [1,2,3,4];
	
	 /**
     * 余额支付方式
     */
	const BALANCE_PAY = 4;
	
	 /**
     * 微信支付
     */
	const WX_PAY = 1;
	
	 /**
     * 天翼支付
     */
	const TY_PAY = 2;
	
	 /**
     * 支付宝支付
     */
	const ALI_PAY = 3;
	
	
	 /**
     * 构造函数
     *
	 * @return void
     */
	private function __construct(){
		$this->init();//初始化
	}
	
	
	//getins
	public static function getIns(){
		if(!empty(self::$ins)){
			return self::$ins;
		}
		return self::$ins = new static();
	}
	
	 /**
     * 初始化
     *
	 * @return void
     */
	protected function init(){
		$method = $this->getPayMethod();//获取支付方式
		$this->setPayMethod($method); //初始化支付方式
	}
	
	
	/**
     * 获取支付方式
     *
     * @return int $order_pay
     */
	private function getPayMethod(){
		//如果是微信支付
		if(isset($GLOBALS['HTTP_RAW_POST_DATA'])){
			return self::WX_PAY;
		}
		return self::WX_PAY;
	}

	
	 /**
     * 设置支付方式
     *
     * @param int $method
     */
	public function setPayMethod($method=self::WX_PAY){
		switch ($method){
			case self::WX_PAY:
			  $this->payMethodModule = new WePayOrder();
			  $this->payMethod = self::WX_PAY;
			  break;  
			case self::TY_PAY:
			   $this->payMethodModule = new TianyiPayOrder();
			   $this->payMethod = self::TY_PAY;
			  break;
			case self::ALI_PAY:
			   $this->payMethodModule = new AliPayOrder();
			   $this->payMethod = self::ALI_PAY;
			  break;
			default:
		}
	}
	
	
	/**
     * 设置回调地址
     * @throws Exception
     * @return string $url
     */
	public function setNotifyUrl($url){
		if(!($this->payMethodModule instanceof PayOrder)){
			throw new Exception('模型错误');
		}
		$this->payMethodModule->notifyUrl = $url;
	}
	
	
	/**
     * 获取回调订单编号
     *
     * @return string $out_trade_no
     */
	protected function getOutTradeNo(){
		return $this->payMethodModule->getOutTradeNo();
	}
	
	/**
     * 返回支付数据
     *
     * @return array $callback_data
     */
	protected function getCallBackData(){
		return $this->payMethodModule->getCallBackData();
	}
	
	/**
     * 支付成功返回码
     *
     * @return string $code
     */
	protected function getCallBackStatus(){
		return $this->payMethodModule->getCallBackStatus();
	}
	
	
	 /**
     * 返回订单提交数据
     *
	 * @return []
     */
	public function getPayInfo(){
		return $this->payMethodModule->getPayInfo();
	}
	
	
	/**
     * 新生产的订单SN
     *
     * @return string $order_sn
     */
	protected function getOrderSn(){
		 return   date('YmdHis') . rand(10000000,99999999);
	}
	
	
}
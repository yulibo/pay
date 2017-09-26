<?php

/**
 * Introduction: 商品订单
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */
namespace Common\Module\Pay;


use Common\Module\Pay\PayWay\PayOrder;
use Common\Model\CityProductSkuModel;
use Common\Model\ShelfModel;

use Common\Module\Pay\PayWay\WePayOrder;
use Common\Module\Pay\PayWay\TianyiPayOrder;
use Common\Module\Pay\PayWay\AliPayOrder;
use Common\Module\Pay\PayWay\BalancePayOrder;
use Common\Module\HonestyOrder as HonestyOrderModule;
use \Exception;

class HonestyOrder extends OrderOpt
{	

	/**
     * @var string 补单描述
     */
	public $desc;
	
	
	 /**
     * 设置支付方式
     *
     * @param int $method
     */
	public function setPayMethod($method=1){
		switch ($method)
		{
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
			case self::BALANCE_PAY:
			   $this->payMethodModule = new BalancePayOrder();
			   $this->payMethod = self::BALANCE_PAY;
			  break;
			default:
		}
	}
	
	


	
	 /**
     * 生成订单前要做的检查
     *
	 * @throws Exception
	 * @return bool 
     */
	private function addOrderPreCheck(){
		try{
			if(empty($this->payMethod)){
				throw new Exception('支付方式不能为空');
			}
			$this->payMethodModule->setOrderData();
			return true;
		}catch(Exception $e){
			throw new Exception($e->getMessage());
			return false;
		}
	}
	
	/**
     * 生成订单
     *
	 * @throws Exception
	 * @return bool 
     */
	public function addOrder($money){
		$this->orderCntMoney = $money;//订单金额
		try{
			$order = $this->initPayOrderData();//初始化订单数据
			$this->addOrderPreCheck();//生成订单前要做的检查
			$bool  = $this->getHonestyModule()->addOrder($order);//生成订单
			if(empty($bool)){
				throw new Exception('添加订单失败');
			}
			$this->createPayOrder();//创建支付订单
			return true;
		}catch(Exception $e){
			$this->error = $e->getMessage(); //错误消息
			$this->errorCode = $e->getCode();//错误号码
			return false;
		}
	}
	
	
	/**
     * 支付回调订单
     *
	 * @throws Exception
	 * @return bool 
     */
	public function callBack(){
		try{
			$data = $this->getCallBackData();//设置回调数据
			$orderSn = $this->getOutTradeNo();//订单号
			if(empty($orderSn)){
				throw new Exception('订单编号不能为空');
			}
			$orderStatus = $this->getCallBackStatus();//支付状态
			$bool = $this->procResult($data);
			if(empty($bool)){
				throw new Exception('回调失败');
			}
			$this->callBackSuccessOpt();//支付回调成功需要做的操作
			return $bool;
		}catch(Exception $e){
			$this->orderSn = $this->getOutTradeNo();//设置回调订单号
			$this->error = $e->getMessage();
			$this->errorCode = $e->getCode();//错误号码
			return false;
		}
	}
	
	 /**
     * 支付回调成功需要做的操作
     *
     * @return void
     */
	public function callBackSuccessOpt(){
		$this->payMethodModule->echoCallBackMsg();//输出回调信息
	}
	
	 /**
     * 如果是余额支付需要设置的操作
     *
     * @return void
     */
	private function balancePaySet(){
		if($this->payMethod==self::BALANCE_PAY){
			$this->payMethodModule->reduceDesc = '支付补单订单号:'.$this->orderSn;
			$this->payMethodModule->pay_type = 2;//消费类型
			$this->payMethodModule->callBackOptFun = [$this,'procResult'];
		}
	}
	
	 /**
     * 订单会调修改
     *
     * @return void
     */
	public function procResult($data){
		return $this->getHonestyModule()->proc_result($data);
	}
	
	
	 /**
     * 创建支付订单
     *
     * @return void 
     */
	private function createPayOrder(){
		$this->balancePaySet();//如果是余额支付需要设置的操作
		return $this->payMethodModule->createPayOrderOpt();
	}
	
	
	 /**
     * 获取订单总额
     *
     * @return float $money
     */
	private function getOrderMoney(){
		return $this->orderCntMoney;
	}
	
	
	 /**
     * 获取货架信息
     *
     * @return array $shelf
     */
	private function getShelfInfo(){
		return $this->getHonestyModule()->getShelfInfo($this->shelfCode,$this->member_id);
	}
	
	 /**
     * 初始化支付数据
     *
     * @return order $order
     */
	private function initPayOrderData(){
		$shelfInfo = $this->getShelfInfo();//货架基本信息
		$this->orderSn = $this->getOrderSn();
		$order = [];
		$order['name'] = $shelfInfo['name'];//货架code 
		$order['member_id'] = $this->member_id;//用户ID
		$order['tel'] = $shelfInfo['tel'];//货架code 
		$order['company_id'] = $shelfInfo['company_id'];//企业ID
		$order['company_name'] = $shelfInfo['company_name'];//企业名称	
		$order['shelf_id'] = $shelfInfo['shelf_id'];//货架ID
		$order['shelf_name'] = $shelfInfo['shelf_name'];//货架名称
		$order['order_money'] = $this->getOrderMoney();//订单金额
		$order['order_sn'] = $this->orderSn; //订单sn 
		$order['created'] = time();//创建时间
		$order['pay_method'] = $this->payMethod;//支付方式
		$order['desc'] = $this->desc;//补单描述
		$order['city'] = $shelfInfo['city'];//城市code
		
		$this->payMethodModule->orderSn = $this->orderSn;//订单编号
		$this->payMethodModule->orderCntMoney = $this->getOrderMoney();//订单总金额
		$this->payMethodModule->orderName = $this->getOrderName();//支付订单名称
		$this->payMethodModule->notifyUrl = C('HONESTY_CALLBACK_URL');//订单回调地址;
		$this->payMethodModule->memberId = $this->member_id; //用户ID
		return $order;
	}
	
	
	 /**
     * 获取诚信补单module
     *
     * @return Common\Module\HonestyOrder
     */
	private function getHonestyModule(){
		return HonestyOrderModule::getIns();
	}
	
	/**
     * 获取订单支付名称
     *
     * @return string $order_name
     */
	private function getOrderName(){
		return '诚信补单';
	}
	

	/**
     * 生产新订单编号
     *
     * @return string $code
     */
	protected function getOrderSn(){
		return   date('YmdHis') . rand(10000,99999);
	}



}
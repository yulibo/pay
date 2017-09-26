<?php

/**
 * Introduction: 商品订单
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */
namespace Common\Module\Pay;


use Common\Module\Pay\PayWay\PayOrder;
use Common\Model\MemberBalanceModel;
use Common\Model\CityProductSkuModel;
use Common\Model\ShelfModel;

use Common\Module\Pay\PayWay\WePayOrder;
use Common\Module\Pay\PayWay\TianyiPayOrder;
use Common\Module\Pay\PayWay\AliPayOrder;
use Common\Module\Pay\PayWay\BalancePayOrder;
use Common\Module\MarketingDiscount;
use Common\Model\MemberMarketingDiscountModel;

use Common\Module\ZwSave;

use \Exception;

class GoodsOrder extends OrderOpt
{	
	
	/**
     * @var int 福柜code
     */
	public $shelfCode;
	
	/**
     * @var int 生成订单的商品list
     */
	public $goodsList;
	
	
	/**
     * @var int 要使用的优惠券ID
     */
	public $marketId;
	
	 /**
     * 不记录订单错误的code
     */
	const NOT_LOG_ERROR_CODE = 9999;
	
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
     * 初始化
     *
	 * @return void
     */
	protected function init(){
		parent::init();
		$this->marketId = intval(I('param.market_id'));
	}
	

	
	 /**
     * 获取当前支付方式
     *
	 * @return int
     */
	private function getPayMethod(){
		//微信
		if($this->payMethod==self::WX_PAY){
			return 2;
		}elseif($this->payMethod==self::ALI_PAY){//支付宝
			return 1;
		}else{
			return $this->payMethod;
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
			$this->checkMarketStatus();//检查优惠券是否可用
			$this->payMethodModule->setOrderData();
			return true;
		}catch(Exception $e){
			throw new Exception($e->getMessage(),self::NOT_LOG_ERROR_CODE);
		}
	}
	
	 /**
     * 获取用户优惠券model
     *
	 * @return Common\Model\MemberMarketingDiscountModel
     */
	private function getMemberMarketingDiscountModel(){
		return new MemberMarketingDiscountModel();
	}
	 
	 
	 /**
     * 检查优惠券是否可用
     *
	 * @throws Exception
     */
	private function checkMarketStatus(){
		if(empty($this->marketId)){
			return false;
		}
		if(empty($this->member_id)){
			throw new Exception('请登录');
		}
		$row = $this->getMemberMarketingDiscountModel()->table('dev_ywy.vm_member_marketing_discount')->where(['id'=>$this->marketId,'member_id'=>$this->member_id])->find();
		$time = time();
		if(empty($row)){
			throw new Exception('优惠券信息不存在');
		}
		if($row['start_time']>$time){
			throw new Exception('优惠券使用时间未开始');
		}
		if($row['end_time']<$time){
			throw new Exception('优惠券已过期');
		}
		//已经被使用的优惠券
		if($row['status']==1){
			throw new Exception('优惠券已经被使用');
		}
		$gqtime = MemberMarketingDiscountModel::GQ_TIME;
		//正在使用中的优惠券
		if($row['status']==2 && $row['modify_time']+$gqtime>time()){
			throw new Exception('优惠券不能使用');
		}
	}
	
	
	 /**
     * 生成订单
     *
	 * @throws Exception
	 * @return bool
     */
	public function addOrder(){
		try{
			$order = $this->initPayOrderData();//初始化订单数据
			$this->addOrderPreCheck();//生成订单前要做的检查
			$money  = $this->getShelfModel()->addOrder($order);//生成订单
			$this->payMethodModule->orderCntMoney = round($money['money'],2);
			$this->createPayOrder();//创建支付订单
			return true;
		}catch(Exception $e){
			$this->error = $e->getMessage(); //错误消息
			$this->errorCode = $e->getCode();//错误号码
			//生成订单失败要做的操作
			$this->addOrderErrOpt();
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
			//生成订单失败要做的操作
			$this->addOrderErrOpt();
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
		if($this->payMethod!=self::BALANCE_PAY){
			return true;
		}
		$this->payMethodModule->reduceDesc = '支付商品订单号:'.$this->orderSn;
		$this->payMethodModule->pay_type = 1;//消费类型
		$this->payMethodModule->callBackOptFun = [$this,'procResult'];
	}
	
	 /**
     * 订单会调修改
     *
     * @return void
     */
	public function procResult($data){
		$bool =  $this->getZwSaveModule()->proc_result($data);
		return $this->getMarketDiscountModule()->updateMarketStatusByOrder($data['out_trade_no']);//修改优惠券信息
	}
	
	 /**
     * 获取优惠券module
     *
     * @return getMarketDiscountModule
     */
	private function getMarketDiscountModule(){
		return MarketingDiscount::getIns();
	}

	
	 /**
     * 创建支付订单
     *
     * @return bool
     */
	private function createPayOrder(){
		$this->balancePaySet();//如果是余额支付需要设置的操作
		$this->getShelfModel()->addTrueCity(['shelf_code'=>$this->shelfCode,'member_id'=>$this->member_id]);//修改用户信息truecity
		return $this->payMethodModule->createPayOrderOpt();
	}
	
	 /**
     * 获取订单总额
     *
     * @return float $money
     */
	private function getOrderMoney(){
		$data =  $this->getShelfModel()->CountSkuMoney($this->shelfCode,$this->goodsList);
		//货架信息
		$shelf    = $data['shelf']; 
		$cur_time = time();
        // 空或者大于等于开始时间
        $start = (empty($shelf['start_time']) || $shelf['start_time'] == NULL) || strtotime($shelf['start_time']) <= $cur_time;
        // 开始时间没问题，结束时间为空或者小于结束时间
        $end = $start && ((empty($shelf['end_time']) || $shelf['end_time'] == NULL) || strtotime($shelf['end_time']) >= $cur_time);
		$sumMomey = $data['sumMomey'];
		if($shelf['busi_type']==2 && $end){
			$sumMomey = $sumMomey * $shelf['rate'];
		}
		//是否有优惠券
		if($this->marketId){
			$row = $this->getMemberMarketingDiscountModel()->table('dev_ywy.vm_member_marketing_discount')->where(['id'=>$this->marketId,'member_id'=>$this->member_id])->find();
			$sumMomey = $sumMomey - $row['gift_num'];
		}
		return round($sumMomey, 2);
	}
	
	
	 /**
     * 初始化支付数据
     *
     * @return order $order
     */
	private function initPayOrderData(){
		$this->orderSn = $this->getOrderSn();
		$order = [];
		$order['code'] = $this->shelfCode;//货架code 
		$order['member_id'] = $this->member_id;//用户ID
		$order['order_sn'] = $this->orderSn; //订单sn 
		$order['payType'] = $this->getPayMethod();//获取支付方式
		$order['list'] = $this->goodsList;
		$order['market_id'] =  $this->getMarketId();//获取优惠券ID
		$this->payMethodModule->orderSn = $this->orderSn;//订单编号
		$this->payMethodModule->orderCntMoney =  round($this->getOrderMoney(),2);//订单总金额
		$this->payMethodModule->orderName = $this->getOrderName();//支付订单名称
		$this->payMethodModule->notifyUrl = C('ORDER_CALLBACK_URL');//订单回调地址;
		$this->payMethodModule->memberId = $this->member_id; //用户ID
		return $order;
	}
	
	 /**
     * 获取post提交优惠券ID
     *
     * @return int $market_id
     */
	private function getMarketId(){
		return trim(I('param.market_id'));//优惠券ID
	}
	
	
	 /**
     * 获取订单支付名称
     *
     * @return string $order_name
     */
	private function getOrderName(){
		if(empty($this->shelfCode) || empty($this->goodsList)){
			return false;
		}
		$model = $this->getShelfModel();
		$shelf = $model->getRow(['shelf_code'=>$this->shelfCode]);//获取福柜
		if(empty($shelf)){
			return false;
		}
		$model = $this->getCityProductSkuModel();//城市基本库
		$skuList = $this->getSkuList();
		$row = $model->getRow(['city_code'=>$shelf['city'],'sku_id'=>$skuList[0]],'pname');
		$count = count($this->goodsList); //sku总数
		return  $row['pname'] . ($count > 1? ('等' . $count . "种商品"): '');
	}
	
	
	/**
     * 获取商品sku列表
     *
     * @return array $sku_list
     */
	private function getSkuList(){
		$result = [];
		foreach($this->goodsList as $val){
			$result[] = $val['sku_id'];
		}
		return $result;
	}
	
	
	/**
     * 生成订单失败要做的操作
     *
     * @return bool $bool
     */
	private function addOrderErrOpt(){
		if($this->errorCode==self::NOT_LOG_ERROR_CODE){
			return true;
		}
		$data = array(
                 'member_id' => $this->member_id,
                 'shelf_code'=> $this->shelfCode,
                 'data'      => json_encode($this->goodsList),
                 'type'      => $this->errorCode,
				 'error_info'=>$this->error,
				 'order_sn'  =>$this->orderSn,
                 'created'   => date('Y-m-d H:i:s',time())
         );
		 return $this->getOrderErrorModule()->addOrderErrOpt($data);
       
	}
	
	/**
     * 获取订单错误module
     *
     * @return ErrorOrderOpt
     */
	private function getOrderErrorModule(){
		return new ErrorOrderOpt();
	}
	

	/**
     * 获取货架model
     *
     * @return Common\Model\ShelfModel
     */
	private function getShelfModel(){
		return new ShelfModel();
	}
	
	
	/**
     * 获取城市商品基本库model
     *
     * @return Common\Model\CityProductSkuModel
     */
	private function getCityProductSkuModel(){
		return new CityProductSkuModel();
	}
	
	 /**
     * 获取订单操作module
     *
     * @return Common\Module\ZwSave
     */
    private function getZwSaveModule(){
       return ZwSave::getIns();
    }


}
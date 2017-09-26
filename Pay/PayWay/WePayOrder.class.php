<?php
/**
 * Introduction: 微信支付
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */

namespace Common\Module\Pay\PayWay;
use Think\Wxpay\Wxpay;
use \Exception;

class WePayOrder  extends PayOrder
{
	const PAY_SUCCESS = 200;//微信支付成功状态码
	
	const CALLBACK_SUCCESS = 'SUCCESS'; //回调成功
	
	const APPID = 'wxac2a54fc8d293824';   //新洋玩易  wxac2a54fc8d293824  点点拿 wx88c66a1811791b74
	const MCHID = '1336166401';    //新洋玩易商户号 1336166401   点点拿商户号： 1250610701
	const KEY = '42E01C1880164A0CBC445B902835F612';
	const APPSECRET = 'c3e737dbfa93c2fa3a0beb3523680d87';  //新洋玩易  c3e737dbfa93c2fa3a0beb3523680d87  点点拿    f3b963ab6edb23ffa22ceaf1b17164e2
	
	
	//创建支付订单
	protected function createPayOrder(){
		$this->payOrderInfo= $this->getWxpayObj()->WeChatPay($this->orderData,'v3');
		//支付失败抛出异常
		if($this->payOrderInfo['code']!=self::PAY_SUCCESS){
			throw new Exception($this->payOrderInfo['msg'],-8);
		}
		return true;
	}
	
	
	//返回订单提交成功的信息
	public function getPayInfo(){
		$this->orderCntMoney = round($this->orderCntMoney,2);
		return ['wxdata'=>$this->payOrderInfo['data'],'orderSn'=>$this->orderSn,'total_money'=>$this->orderCntMoney];
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
		if(empty($this->notifyUrl)){
			throw new Exception('订单回调地址不能为空');
		}
		$orderData = [];
		$orderData['buyer_name'] = $this->orderName;
        $orderData['order_id'] = $this->orderSn;
        $orderData['order_amount'] = $this->orderCntMoney*100;
		$orderData['notify_url'] = $this->notifyUrl;
		return $this->orderData=$orderData;
	}
	
	
	/**
	* 获取订单编号
	* @return 值
	**/
	public function getOutTradeNo(){
		return isset($this->callBackData['out_trade_no'])?$this->callBackData['out_trade_no']:'';
	}
	
	
	/**
	* 获取支付返回码
	* @return 值
	**/
	public function getCallBackStatus(){
		$returnCode =  isset($this->callBackData['return_code'])?$this->callBackData['return_code']:'';
		if($returnCode==self::CALLBACK_SUCCESS){
			return 1;
		}else{
			return 2;
		}
	}
	
	//获取回调数据
	private function getCallBackDataAll(){
		//获取通知的数据
		$xml = $GLOBALS['HTTP_RAW_POST_DATA'];		
		file_put_contents('rechargerCallBack.txt',var_export($xml,true),8);
		if(!$xml){
			throw new Exception("xml数据异常！",-8);
		}
        //将XML转为array 
        $this->callBackData = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		$this->checkSign();//检测签名
		return $this->callBackData;
	}
	
	//输出会调信息
	public function echoCallBackMsg(){
		echo $GLOBALS['HTTP_RAW_POST_DATA'];
	}
	
	
	 /**
     * 将xml转为array
     * @param string $xml
     * @throws Exception
     */
	public function getCallBackData(){
		$this->getCallBackDataAll();//获取回调数据
		$result['out_trade_no'] = $this->callBackData['out_trade_no']; ////订单编号
		$result['transaction_id'] =  $this->callBackData['transaction_id'];////事务ID
		$result['mch_id'] =  $this->callBackData['mch_id'];////商户ID
		return $result;
	}

	
	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	private function makeSign()
	{
		//签名步骤一：按字典序排序参数
		ksort($this->callBackData);
		$string = $this->toUrlParams();
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".self::KEY;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
	
	
	
	/**
	* 获取签名，详见签名生成算法的值
	* @return 值
	**/
	private function getSign()
	{
		return isset($this->callBackData['sign'])?$this->callBackData['sign']:'';
	}
	
	
	/**
	 * 格式化参数格式化成url参数
	 */
	private function toUrlParams()
	{
		$buff = "";
		foreach ($this->callBackData as $k => $v)
		{
			if($k != "sign" && $v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}
	
	
	/**
	 * 
	 * 检测签名
	 */
	private function checkSign()
	{
		$sign = $this->makeSign();
		if($this->getSign() == $sign){
			return true;
		}
		throw new Exception("签名错误！",-8);
	}
	
	
   
    //获取微信支付obj
    private function getWxpayObj(){
	   return new Wxpay();
    }
	
}
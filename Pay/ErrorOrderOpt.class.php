<?php

/**
 * Introduction: 订单错误
 * @author: ylb
 * @date: 2017/8/21
 * @email: 344138191@qq.com
 */
namespace Common\Module\Pay;


use Common\Model\ShelfModel;
use Org\Util\MailUtil;


use Common\Module\ZwSave;

use \Exception;

class ErrorOrderOpt
{	
	
	/**
     * @var array 订单状态对应错误号码
     */
	public static $orderErrorList=[
		300=>1,//生成订单失败
		-3=>3,//回调修改订单
		-8=>2,//支付订单
	];
	
	 /**
     * 邮件回复host
     */
	const REPLAY_HOST = 'smtp.mxhichina.com';
	
	 /**
     * 邮件回复端口
     */
	const MAIL_PORT = 25;
	
	 /**
     * 收件邮箱
     */
	const MAIL_USER = 'system@yonwan.cn';
	
	 /**
     * 邮箱密码
     */
	const MAIL_PASSWORD = 'skfBdk234';
	
	
	
	 /**
     * 生成订单失败要做的操作
     *
	 * @return void
     */
	public function addOrderErrOpt($data){
		 if(empty($data)){
			 return false;
		 }
		 //错误码对应
		 if(isset(self::$orderErrorList[$data['type']])){
			$data['type'] = self::$orderErrorList[$data['type']];
		 }
         $this->getOrderErrorLogModel()->add($data);
		 $this->sendFailMail($data);
	}
	
	 /**
     * 发生失败邮件
     *
	 * @return void
     */
	private function sendFailMail($data=array())
    {
        $replay_host = self::REPLAY_HOST;
        $port        = self::MAIL_PORT;
        $user        = self::MAIL_USER;
        $passwd      = self::MAIL_PASSWORD;

        $mail = new MailUtil();
        $mail->MailUtil($replay_host, $port, true, $user, $passwd);
        $content = '<h3>Master,你好！</h3>'
                . '<p style="text-indent: 20px;">【'. $data['member_id'] .'】<span style="color:red">下单失败</span>，相关信息如下：</p>'
                . '<p style="padding-left: 25px; line-height: 20px;">'
                . '虚拟编号：'. $data['shelf_code'] .'<br>  ' 
        
                . '提交数据：'. $data['data'] .'<br>'
                . '时间：'. $data['created'].'<br>'
                . '数据来自福柜扫码<br></p>'

                . '<p style="text-indent: 20px; text-align:right;">来自洋玩易技术团队</p>';
        $res = $mail->sendmail('rogerwei@yonwan.cn', $user, '下单失败--福柜扫码', $content, 'HTML');
    }
	

	/**
     * 获取订单错误model
     *
	 * @return Think\Model
     */
	private function getOrderErrorLogModel(){
		return M('dev_ywy.order_error_log', 'vm_');
	}
	
	
}
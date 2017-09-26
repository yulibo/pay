<?php
/**

 */
namespace Common\Module;

abstract class Module
{
    public $error;//错误信息
	public $errorCode;//错误code
    protected static $ins;//ins
    public $member_id;//用户ID


    final private function __construct(){
		$this->member_id = session('member_id');
		$this->init();//初始化
    }
	
	//初始化
	abstract protected function init();
	
	
    public static function getIns(){  
        if(!empty(static::$ins)){
            return static::$ins;
        }
        return static::$ins = new static();   
    }
}
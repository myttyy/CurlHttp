<?php
namespace myttyy;

/**
 * Http请求类
 * 提供GET、POST、同步/异步
 * @author myttyy 1297942619@qq.com
 */

use myttyy\driver\Directory as HttpDriver;

class Http 
{
    private $link =null;
    public function __call( $method, $params ) {
		if ( is_null( $this->link ) ) {
			$this->link = new HttpDriver();
		}
		if ( method_exists( $this->link, $method ) ) {
			return call_user_func_array( [ $this->link, $method ], $params );
		}
    }
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(array(new HttpDriver(), $name), $arguments);
    }
}

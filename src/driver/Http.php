<?php
namespace myttyy\driver;

use myttyy\driver\MakeRequest;

class Http 
{
    /** 
     * 请求url个数 
     * @var int 
     */  
    private $size           = 5;  

    /** 
     * 等待所有cURL批处理中的活动连接等待响应时间 
     * @var int 
     */  
    private $timeout        = 5;  

    /** 
     * 完成请求回调函数 
     * @var string 
     */  
    private $callback       = "multi_http_callback"; 

    /** 
     * cRUL配置 
     * @var array 
     */  
    private $options        = [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => 30 
    ];  

    /** 
     * 请求头 
     * @var array 
     */  
    private $headers        = [];  

    /** 
     * 请求列队 
     * @var array 
     */  
    private $requests       = [];  

    /** 
     * 请求列队索引 
     * @var array 
     */  
    private $map    = [];  

    /** 
     * 错误 
     * @var array 
     */  
    private $errors         = []; 

     /** 
     * @access public 
     * @param string $callback 回调函数 
     * 该函数有4个参数($response,$info,$error,$request) 
     * $response    url返回的body 
     * $info        cURL连接资源句柄的信息 
     * $error       错误 
     * $request     请求对象 
     */  
    public function __construct($callback = null) {  
        $this->callback = $callback;  
    }  

    /** 
     * 添加一个请求对象到列队 
     * @access public 
     * @param object $request 
     * @return boolean 
     */  
    public function addRequests(MakeRequest $request):bool {  
        $this->requests[] = $request;  
        return TRUE;  
    }  

    /** 
     * 创建一个请求对象并添加到列队 
     * @access public 
     * @param string $url 
     * @param string $method  
     * @param array $param 
     * @param array $headers 
     * @param array $options 
     * @return boolean 
     */  
    public function request(string $url,string $method = 'post',?array $param = null,?array $headers = null,?array $options = null):bool {  
        $this->requests[] = new MakeRequest ( $url, $method, $param, $headers, $options );  
        return TRUE;  
    }  

    /** 
     * 创建GET请求对象 
     * @access public 
     * @param string $url 
     * @param string $headers 
     * @param array $options 
     * @return boolean 
     */  
    public function get(string $url,string $headers = null,?array $options = []):bool {  
        return $this->request ( $url, "GET", null, $headers, $options );  
    }  
    
    /** 
     * 创建一个POST请求对象 
     * @access public 
     * @param string $url 
     * @param array $param 
     * @param string $headers 
     * @param array $options 
     * @return boolean 
     */  
    public function post(string $url,?array $param = null,?array $headers = null,?array $options = null):bool {  
        return $this->request ( $url, "POST", $param, $headers, $options );  
    }  

    /** 
     * 执行cURL 
     * @access public 
     * @param int $size 最大连接数 
     * @return Ambigous <boolean, mixed>|boolean 
     */  
    public function execute($size = null) {  
        if (sizeof ( $this->requests ) == 1) {  
            return $this->single_curl ();  
        } else {  
            return $this->rolling_curl ( $size );  
        }  
    }  
    
    /** 
     * 单个url请求 
     * @access private 
     * @return mixed|boolean 
     */  
    private function single_curl() {  
        $ch = curl_init ();  
        $request = array_shift ( $this->requests );  
        $options = $this->get_options ( $request );  
        curl_setopt_array ( $ch, $options );  
        $output = curl_exec ( $ch );  
        $info = curl_getinfo ( $ch );  
          
        // it's not neccesary to set a callback for one-off requests  
        if ($this->callback) {  
            $callback = $this->callback;  
            if (is_callable ( $this->callback )) {  
                call_user_func ( $callback, $output, $info, $request );  
            }  
        } else  
            return $output;  
        return true;  
    }  

    /** 
     * 多个url请求 
     * @access private 
     * @param int $size 最大连接数 
     * @return boolean 
     */  
    private function rolling_curl(int $size = null):bool {  
        if ($size)  
            $this->size = $size;  
        else   
            $this->size = count($this->requests);  
        if (sizeof ( $this->requests ) < $this->size)  
            $this->size = sizeof ( $this->requests );  
        if ($this->size < 2)  
            $this->set_error ( 'size must be greater than 1' );  
        $master = curl_multi_init ();  
        //添加cURL连接资源句柄到map索引  
        for($i = 0; $i < $this->size; $i ++) {  
            $ch = curl_init ();  
            $options = $this->get_options ( $this->requests [$i] );  
            curl_setopt_array ( $ch, $options );  
            curl_multi_add_handle ( $master, $ch );  
              
            $key = ( string ) $ch;  
            $this->map [$key] = $i;  
        }  
          
        $active = $done = null;  
        do {  
            while ( ($execrun = curl_multi_exec ( $master, $active )) == CURLM_CALL_MULTI_PERFORM )  
                ;  
            if ($execrun != CURLM_OK)  
                break;  
            //有一个请求完成则回调  
            while ( $done = curl_multi_info_read ( $master ) ) {  
                //$done 完成的请求句柄  
                $info = curl_getinfo ( $done ['handle'] );//  
                $output = curl_multi_getcontent ( $done ['handle'] );//  
                $error = curl_error ( $done ['handle'] );//  
                  
                $this->set_error ( $error );  
                  
                //调用回调函数,如果存在的话  
                $callback = $this->callback;  
                if (is_callable ( $callback )) {  
                    $key = ( string ) $done ['handle'];  
                    $request = $this->requests [$this->map [$key]];  
                    unset ( $this->map [$key] );  
                    call_user_func ( $callback, $output, $info, $error, $request );  
                }  
                curl_close ( $done ['handle'] );  
                //从列队中移除已经完成的request  
                curl_multi_remove_handle ( $master, $done ['handle'] );  
            }  
            //等待所有cURL批处理中的活动连接  
            if ($active)  
                curl_multi_select ( $master, $this->timeout );  
        } while ( $active );  
        //完成关闭  
        curl_multi_close ( $master );  
        return true;  
    }  

    /** 
     * 获取没得请求对象的cURL配置 
     * @access private 
     * @param object $request 
     * @return array 
     */  
    private function get_options($request) {  
        $options = $this->__get ( 'options' );  
        if (ini_get ( 'safe_mode' ) == 'Off' || ! ini_get ( 'safe_mode' )) {  
            $options [CURLOPT_FOLLOWLOCATION] = 1;  
            $options [CURLOPT_MAXREDIRS] = 5;  
        }  
        $headers = $this->__get ( 'headers' );  
          
        if ($request->options) {  
            $options = $request->options + $options;  
        }  
          
        $options [CURLOPT_URL] = $request->url;  
          
        if ($request->param && strtolower($request->method) == 'post' ) {  
            $options [CURLOPT_POST] = 1;  
            $options [CURLOPT_POSTFIELDS] = $request->param;  
        }  
        if ($headers) {  
            $options [CURLOPT_HEADER] = 0;  
            $options [CURLOPT_HTTPHEADER] = $headers;  
        }  
          
        return $options;  
    } 
     
    /** 
     * 设置错误信息 
     * @access public 
     * @param string $msg 
     */  
    public function set_error($msg) {  
        if (! empty ( $msg ))  
            $this->errors [] = $msg;  
    }  

    /** 
     * 获取错误信息 
     * @access public 
     * @return string 
     */  
    public function display_errors($lineFeed = "</br>") {  
        $str = '';  
        foreach ( $this->errors as $val ) {  
            $str .=  $val . $lineFeed;  
        }  
        return $str;  
    }  

    /** 
     * @access public 
     * @param string $name 
     * @param string $value 
     * @return boolean 
     */  
    public function __set($name, $value) {  
        if ($name == 'options' || $name == 'headers') {  
            $this->{$name} = $value + $this->{$name};  
        } else {  
            $this->{$name} = $value;  
        }  
        return TRUE;  
    }  

    /** 
     *  
     * @param string $name 
     * @return mixed 
     * @access public 
     */  
    public function __get($name) {  
        return (isset ( $this->{$name} )) ? $this->{$name} : null;  
    }  

    /** 
     * @return void 
     * @access public 
     */  
    public function __destruct() {  
        unset ( $this->size, $this->timeout, $this->callback, $this->options, $this->headers, $this->requests, $this->map, $this->errors );  
    }  
}

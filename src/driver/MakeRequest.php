<?php
namespace myttyy\driver;

/**
 * 构建请求对象
 */

class MakeRequest
{
    public $url = '';
    public $method = 'GET';
    public $param = null;
    public $headers = null;
    public $options = null;

    /**
     *
     * @param string $url
     * @param string $method
     * @param string $param
     * @param string $headers
     * @param array $options
     * @return void
     */
    public function __construct(string $url, string $method = 'GET', ?array $param = [], ?array $headers = [], ?array $options = [])
    {
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->param = $param;
        $this->headers = $headers;
        $this->options = $options;
    }
    /**
     * @return void
     */
    public function __destruct()
    {
        unset($this->url, $this->method, $this->param, $this->headers, $this->options);
    }
}

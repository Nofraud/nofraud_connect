<?php
namespace Magento\Framework\HTTP\Client;

class Curl
{
    public function setHeaders($headers) {}
    public function addHeader($name, $value) {}
    public function setOption($name, $value) {}
    public function post($uri, $params) {}
    public function get($uri) {}
    public function getStatus() { return 0; }
    public function getBody() { return ''; }
}

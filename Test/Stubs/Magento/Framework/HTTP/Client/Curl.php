<?php
namespace Magento\Framework\HTTP\Client;

class Curl
{
    public function setHeaders(array $headers) {}
    public function addHeader($name, $value) {}
    public function setOption($name, $value) {}
    public function post($url, $body) {}
    public function get($url) {}
    public function getStatus() { return 200; }
    public function getBody() { return ''; }
}

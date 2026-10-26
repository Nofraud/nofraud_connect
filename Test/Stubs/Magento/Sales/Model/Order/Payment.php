<?php
namespace Magento\Sales\Model\Order;

class Payment
{
    public function getCcAvsStatus() { return null; }
    public function getCcCidStatus() { return null; }
    public function getCcType() { return null; }
    public function getCcNumber() { return null; }
    public function getCcCid() { return null; }
    public function getCcExpMonth() { return null; }
    public function getCcExpYear() { return null; }
    public function getCcLast4() { return null; }
    public function getMethod() { return ''; }
    public function decrypt($data) { return $data; }
}

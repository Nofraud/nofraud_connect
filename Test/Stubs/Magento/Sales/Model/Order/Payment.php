<?php
namespace Magento\Sales\Model\Order;

class Payment
{
    public function getMethod() { return ''; }
    public function getCcAvsStatus() { return ''; }
    public function getCcCidStatus() { return ''; }
    public function getCcCid() { return ''; }
    public function getCcType() { return ''; }
    public function getCcLast4() { return ''; }
    public function getCcExpMonth() { return ''; }
    public function getCcExpYear() { return ''; }
    public function getCcNumber() { return ''; }
    public function getCcNumberEnc() { return ''; }
    public function getAdditionalInformation($key = null) { return []; }
    public function getMethodInstance() { return null; }
}

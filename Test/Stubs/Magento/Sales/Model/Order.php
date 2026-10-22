<?php
namespace Magento\Sales\Model;

class Order
{
    public function getIncrementId() { return ''; }
    public function getGrandTotal() { return 0; }
    public function getOrderCurrencyCode() { return ''; }
    public function getShippingAmount() { return 0; }
    public function getRemoteIp() { return ''; }
    public function getXForwardedFor() { return ''; }
    public function getQuoteId() { return null; }
    public function getStoreId() { return null; }
}

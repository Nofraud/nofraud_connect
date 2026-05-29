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
    public function getCustomerEmail() { return ''; }
    public function getCustomerId() { return null; }
    public function getBillingAddress() { return null; }
    public function getShippingAddress() { return null; }
    public function getItems() { return []; }
    public function getPayment() { return null; }
    public function getStoreId() { return null; }
    public function getStore() { return null; }
    public function getCustomerIsGuest() { return true; }
    public function getCustomerFirstname() { return ''; }
    public function getCustomerLastname() { return ''; }
    public function getCreatedAt() { return ''; }
}

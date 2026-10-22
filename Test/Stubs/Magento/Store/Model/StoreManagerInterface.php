<?php
namespace Magento\Store\Model;

interface StoreManagerInterface
{
    public function getStore($storeId = null);
    public function getStores($withDefault = false);
}

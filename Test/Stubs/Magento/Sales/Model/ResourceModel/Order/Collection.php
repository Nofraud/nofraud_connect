<?php
namespace Magento\Sales\Model\ResourceModel\Order;

class Collection
{
    public function addFieldToSelect($field) { return $this; }
    public function setOrder($field, $direction = 'DESC') { return $this; }
    public function getSelect() { return null; }
}

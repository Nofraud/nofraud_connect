<?php
namespace Magento\Framework;

class Event extends DataObject
{
    public function getOrder() { return $this->getData('order'); }
}

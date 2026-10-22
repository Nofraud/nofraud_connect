<?php
namespace Magento\Framework\Event;

class Observer extends \Magento\Framework\DataObject
{
    public function getEvent() { return $this->getData('event'); }
}

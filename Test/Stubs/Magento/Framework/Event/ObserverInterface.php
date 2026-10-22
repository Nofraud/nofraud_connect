<?php
namespace Magento\Framework\Event;

interface ObserverInterface
{
    public function execute(Observer $observer);
}

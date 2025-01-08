<?php

namespace NoFraud\Connect\Plugin;

use ParadoxLabs\TokenBase\Observer\SetInitialOrderStatusObserver;
use Magento\Framework\Event\Observer;

class SetInitialOrderStatusPlugin
{
    /**
     * Around plugin for execute method.
     *
     * @param SetInitialOrderStatusObserver $subject
     * @param callable $proceed
     * @param Observer $observer
     * @return void
     */
    public function aroundExecute(
        SetInitialOrderStatusObserver $subject,
        callable $proceed,
        Observer $observer
    ) {
        // Return without calling $proceed to stop original logic
    }
}

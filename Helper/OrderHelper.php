<?php

namespace NoFraud\Connect\Helper;

use \Magento\Sales\Model\Order as Order;


class OrderHelper
{
    /**
     * Retrieve reasons why an order is not invoicable
     *
     * @param Order $order
     * @return array
     */
    public function getInvoiceBlockingReasons(Order $order): array
    {
        $reasons = [];

        if ($order->canUnhold()) {
            $reasons[] = 'Order can be unheld';
        }

        if ($order->isPaymentReview()) {
            $reasons[] = 'Order is in payment review';
        }

        $state = $order->getState();
        if ($order->isCanceled()) {
            $reasons[] = 'Order is canceled';
        }

        if (in_array($state, [Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
            $reasons[] = 'Order state is complete or closed';
        }

        if ($order->getActionFlag(Order::ACTION_FLAG_INVOICE) === false) {
            $reasons[] = 'Invoicing is disabled for this order';
        }

        $hasInvoicableItems = false;
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                $hasInvoicableItems = true;
                break;
            }
        }

        if (!$hasInvoicableItems) {
            $reasons[] = 'No items are available to invoice';
        }

        if ($reasons === []) {
            $reasons[] = 'Unknown reason';
        }

        return $reasons;
    }
}
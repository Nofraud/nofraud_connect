<?php

namespace NoFraud\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment;

class DenyPaymentAfterOrder implements ObserverInterface
{
    private const BRAINTREE_CODE = 'braintree';

    /**
     * @var \NoFraud\Connect\Helper\Data
     */
    private $dataHelper;
    /**
     * @var \NoFraud\Connect\Helper\Config
     */
    private $configHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        \NoFraud\Connect\Helper\Data $dataHelper,
        \NoFraud\Connect\Helper\Config $configHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
    ) {
        $this->dataHelper = $dataHelper;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();
        $storeId = $this->storeManager->getStore()->getId();
        $isRefundOnline = $this->configHelper->getRefundOnline($storeId);
        // This calls the "deny" method *after* the order is placed
        // so the Thank You page is already shown to the customer.

        try {
            if ($isRefundOnline && $payment->getMethod() === self::BRAINTREE_CODE) {
                $payment->deny();
            }
        } catch (\Exception $e) {
            $this->dataHelper->addErrorToLog($e->getMessage());
        }

        return $this;
    }
}

<?php

namespace NoFraud\Connect\Order;

use Magento\Sales\Model\Order;
use Magento\Framework\Serialize\Serializer\Json;

class Processor
{
    protected $logger;
    protected $configHelper;
    protected $dataHelper;
    protected $orderStatusCollection;
    protected $stateIndex = [];
    protected $orderManagement;
    protected $orderStatusFactory;
    protected $storeManager;
    protected $refundInvoiceInterface;
    protected $invoiceRepositoryInterface;

    const CYBERSOURCE_METHOD_CODE = 'md_cybersource';

    public function __construct(
        \NoFraud\Connect\Logger\Logger $logger,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        \NoFraud\Connect\Helper\Config $configHelper,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\StatusFactory $orderStatusFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\RefundInvoiceInterface $refundInvoiceInterface,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepositoryInterface
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->orderStatusCollection = $orderStatusCollection;
        $this->configHelper = $configHelper;
        $this->orderManagement = $orderManagement;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->storeManager = $storeManager;
        $this->refundInvoiceInterface = $refundInvoiceInterface;
        $this->invoiceRepositoryInterface = $invoiceRepositoryInterface;
    }

    public function getCustomOrderStatus($response, $storeId = null)
    {
        if (isset($response['body']['decision'])) {
            error_log("\n getCustomOrderStatus decision ",3, BP."/var/log/orderstatud.log");
            $statusName = $response['body']['decision'];
        }

        if (isset($response['code'])) {
            if ($response['code'] > 299) {
                $statusName = 'error';
                error_log("\n getCustomOrderStatus ERROR ",3, BP."/var/log/orderstatud.log");
            }
        }

        if (isset($statusName)) {
            return $this->configHelper->getCustomStatusConfig($statusName, $storeId);
        }
    }

    public function updateOrderStatusFromNoFraudResult($noFraudOrderStatus, $order) 
    {
        if (!empty($noFraudOrderStatus)) {
            $newState = $this->getStateFromStatus($noFraudOrderStatus);
            error_log("updateOrderStatusFromNoFraudResult ".$newState,3, BP."/var/log/orderstatud.log");
            if ($newState == Order::STATE_HOLDED) {
                $order->hold();
            } else if ($newState) {
                $order->setStatus($noFraudOrderStatus)->setState($newState);
            }
        }
    }

    public function getStateFromStatus($state)
    {
        $statuses = $this->orderStatusCollection->create()->joinStates();

        if (empty($this->stateIndex)) {
            foreach ($statuses as $status) {
                $this->stateIndex[$status->getStatus()] = $status->getState();
            }
        }

        return $this->stateIndex[$state] ?? null;
    }

    public function handleAutoCancel($order, $decision)
    {
        error_log("\n INSIDE  ",3, BP."/var/log/orderstatud.log");
        // if order failed NoFraud check, try to refund and cancel order
        if ($decision == 'fail' || $decision == 'fraudulent'){
            error_log("\n BEFORE refundOrder ",3, BP."/var/log/orderstatud.log");
            $this->refundOrder($order);
            error_log("\n AFTER refundOrder ",3, BP."/var/log/orderstatud.log");
            // Handle custom cancel for Payment Method if needed
            if(!$this->_runCustomAutoCancel($order)){
                error_log("\n _runCustomAutoCancel ",3, BP."/var/log/orderstatud.log");
                $order->cancel();
                $order->setState(Order::STATE_CANCELED)->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
                $order->save();
            }
        }
    }

    private function _runCustomAutoCancel($order){
        $isCustom = true;
        $method = $order->getPayment()->getMethod();

        switch ($method){
            case (self::CYBERSOURCE_METHOD_CODE):
                $this->_handleCyberSourceAutoCanel($order);
                break;
            default:
                $isCustom = false;
                break;
        }

        return $isCustom;
    }

    private function _handleCyberSourceAutoCanel($order){
        $this->orderManagement->cancel($order->getEntityId());

        $status = $this->orderStatusFactory->create()->loadDefaultByState(Order::STATE_CANCELED)->getStatus();
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus($status);

        $order->save();
    }

    public function refundOrder($order){
        error_log("\n inside the refun".$order->getId(),3, BP."/var/log/orderstatud.log");
        $storeId = $this->storeManager->getStore()->getId();
        $isOffline = $order->getPayment()->getMethodInstance()->isOffline();
        error_log("\n inside the refun IS OFFLINE 1: ".var_export($isOffline, true),3, BP."/var/log/orderstatud.log");
        error_log("\n inside the refun IS OFFLINE 2: ".$this->configHelper->getRefundOnline($storeId),3, BP."/var/log/orderstatud.log");
        // If payment method is online, attempt online refund if enabled
        $isRefundOnline = $this->configHelper->getRefundOnline($storeId);
        if (isset($isOffline) && !$isOffline && $isRefundOnline) {
            error_log("\n inside the refun can invoice refund ",3, BP."/var/log/orderstatud.log");
            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice){
                try {
                    if($invoice->canRefund()) {
                        error_log("This order can be refunded".$invoice->canRefund(),3, BP."/var/log/orderstatud.log");
                        $this->refundInvoiceInterface->execute($invoice->getId(), [], true);
                        error_log("This order can be refunded".$invoice->canRefund(),3, BP."/var/log/orderstatud.log");
                    }elseif($invoice->canVoid()){
                        error_log("\n before void ".$invoice->canRefund(),3, BP."/var/log/orderstatud.log");
                        $invoice->void();
                        error_log("\n After void ".$invoice->canRefund(),3, BP."/var/log/orderstatud.log");
                    }
                }
                catch (\Exception $e){
                    $this->logger->logRefundException($e, $order->getId());
                }
            }
        }
        return true;
    }
}

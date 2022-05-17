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
            $statusName = $response['body']['decision'];
        }

        if (isset($response['code'])) {
            if ($response['code'] > 299) {
                $statusName = 'error';
            }
        }

        if (isset($statusName)) {
            return $this->configHelper->getCustomStatusConfig($statusName, $storeId);
        }
    }

    public function updateOrderStatusFromNoFraudResult($noFraudOrderStatus, $order,$response) 
    {
        if (!empty($noFraudOrderStatus)) {
            $newState = $this->getStateFromStatus($noFraudOrderStatus);
            if ($newState == Order::STATE_HOLDED) {
                $order->hold();
            } else if ($newState) {
                $order->setStatus($noFraudOrderStatus)->setState($newState);
                if( isset($response['http']['response']['body']['decision']) && ($response['http']['response']['body']['decision'] == 'pass') ){
                    $order->setNofraudStatus($response['http']['response']['body']['decision']);
                }
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
        // if order failed NoFraud check, try to refund and cancel order
        if ($decision == 'fail' || $decision == 'fraudulent'){
            $this->refundOrder($order);
            // Handle custom cancel for Payment Method if needed
            if(!$this->_runCustomAutoCancel($order)){
                $order->cancel();
                $order->setNofraudStatus($decision);
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
        $storeId = $this->storeManager->getStore()->getId();
        $isOffline = $order->getPayment()->getMethodInstance()->isOffline();
        // If payment method is online, attempt online refund if enabled
        $isRefundOnline = $this->configHelper->getRefundOnline($storeId);
        if (isset($isOffline) && !$isOffline && $isRefundOnline) {
            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice){
                try {
                    if($invoice->canRefund()) {
                        $this->refundInvoiceInterface->execute($invoice->getId(), [], true);
                    }elseif($invoice->canVoid()){
                        $invoice->void();
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

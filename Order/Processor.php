<?php

namespace NoFraud\Connect\Order;

use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\RefundInvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Helper\Data;
use NoFraud\Connect\Logger\Logger;

/**
 * Process Orders to the NF server
 */
class Processor
{
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var Config
     */
    protected $configHelper;
    /**
     * @var Data
     */
    protected $dataHelper;
    /**
     * @var CollectionFactory
     */
    protected $orderStatusCollection;
    /**
     * @var array
     */
    protected $stateIndex = [];
    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;
    /**
     * @var StatusFactory
     */
    protected $orderStatusFactory;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var RefundInvoiceInterface
     */
    protected $refundInvoiceInterface;
    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepositoryInterface;

    const CYBERSOURCE_METHOD_CODE = 'md_cybersource';

    /**
     * @param Logger $logger
     * @param Data $dataHelper
     * @param CollectionFactory $orderStatusCollection
     * @param Config $configHelper
     * @param OrderManagementInterface $orderManagement
     * @param StatusFactory $orderStatusFactory
     * @param StoreManagerInterface $storeManager
     * @param RefundInvoiceInterface $refundInvoiceInterface
     * @param InvoiceRepositoryInterface $invoiceRepositoryInterface
     */
    public function __construct(
        Logger $logger,
        Data $dataHelper,
        CollectionFactory $orderStatusCollection,
        Config $configHelper,
        OrderManagementInterface $orderManagement,
        StatusFactory $orderStatusFactory,
        StoreManagerInterface $storeManager,
        RefundInvoiceInterface $refundInvoiceInterface,
        InvoiceRepositoryInterface $invoiceRepositoryInterface
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

    /**
     * Get order decision from NF server
     * @param $response
     * @param null $storeId
     * @return false|mixed|void
     */
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

    /**
     * Update Order Status from NF
     * @param $noFraudOrderStatus
     * @param $order
     * @param string $decision
     * @return void
     */
    public function updateOrderStatusFromNoFraudResult($noFraudOrderStatus, $order, $decision = '')
    {
        if (!empty($noFraudOrderStatus)) {
            $newState = $this->getStateFromStatus($noFraudOrderStatus);

            if ($newState == Order::STATE_HOLDED) {
                $order->hold();
            } elseif ($newState) {
                $order->setStatus($noFraudOrderStatus)->setState($newState);
                $decision == '' ?: $order->setNofraudStatus($decision);
            }
        }
    }

    /**
     * Get order state from status
     * @param $state
     * @return mixed|null
     */
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

    /**
     * @param $order
     * @param $decision
     */
    public function handleAutoCancel($order, $decision)
    {
        // if order failed NoFraud check, try to refund and cancel order
        if ($decision == 'fail') {
            $this->refundOrder($order);
            // Handle custom cancel for Payment Method if needed
            if (!$this->runCustomAutoCancel($order)) {
                $order->cancel();
                $order->setState(Order::STATE_CANCELED)->setStatus($order->getConfig()
                    ->getStateDefaultStatus(Order::STATE_CANCELED));
                $order->save();
            }
        }
    }

    /**
     * @param $order
     * @return bool
     */
    private function runCustomAutoCancel($order)
    {
        $isCustom = true;
        $method = $order->getPayment()->getMethod();

        switch ($method) {
            case (self::CYBERSOURCE_METHOD_CODE):
                $this->handleCyberSourceAutoCanel($order);
                break;
            default:
                $isCustom = false;
                break;
        }

        return $isCustom;
    }

    /**
     * @param $order
     * @return void
     */
    private function handleCyberSourceAutoCanel($order)
    {
        $this->orderManagement->cancel($order->getEntityId());

        $status = $this->orderStatusFactory->create()->loadDefaultByState(Order::STATE_CANCELED)->getStatus();
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus($status);

        $order->save();
    }

    /**
     * @param $order
     * @return void
     */
    public function refundOrder($order)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isOffline = $order->getPayment()->getMethodInstance()->isOffline();

        // If payment method is online, attempt online refund if enabled
        if (!$isOffline && $this->configHelper->getRefundOnline($storeId)) {
            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                try {
                    $this->refundInvoiceInterface->execute($invoice->getId(), [], true);
                } catch (\Exception $e) {
                    $this->logger->logRefundException($e, $order->getId());
                }
            }
        }
    }
}

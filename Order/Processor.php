<?php

namespace NoFraud\Connect\Order;

use Magento\Sales\Model\Order;
use Magento\Framework\Serialize\Serializer\Json;

class Processor
{
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    /**
     * @var DataHelper
     */
    protected $dataHelper;
    /**
     * @var OrderStatusCollection
     */
    protected $orderStatusCollection;
    /**
     * @var StateIndex
     */
    protected $stateIndex = [];
    /**
     * @var OrderManagement
     */
    protected $orderManagement;
    /**
     * @var OrderStatusFactory
     */
    protected $orderStatusFactory;
    /**
     * @var StoreManager
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
    /**
     * @var \NoFraud\Connect\Service\InvoiceService
     */
    protected $nofraudInvoiceService;

    private const CYBERSOURCE_METHOD_CODE = 'md_cybersource';

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param \NoFraud\Connect\Helper\Data $dataHelper
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection
     * @param \NoFraud\Connect\Helper\Config $configHelper
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Magento\Sales\Model\Order\StatusFactory $orderStatusFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Api\RefundInvoiceInterface $refundInvoiceInterface
     * @param \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepositoryInterface
     * @param \NoFraud\Connect\Service\InvoiceService $nofraudInvoiceService
     */
    public function __construct(
        \NoFraud\Connect\Logger\Logger $logger,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        \NoFraud\Connect\Helper\Config $configHelper,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\StatusFactory $orderStatusFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\RefundInvoiceInterface $refundInvoiceInterface,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepositoryInterface,
        \NoFraud\Connect\Service\InvoiceService $nofraudInvoiceService,
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
        $this->nofraudInvoiceService = $nofraudInvoiceService;
    }

    /**
     * Get Customer Order Status
     *
     * @param mixed $response
     * @param mixed $storeId
     */
    public function getCustomOrderStatus($response, $storeId = null)
    {
        if (isset($response['body']['decision'])) {
            $statusName = $response['body']['decision'];
            if ($statusName == 'fraudulent') {
                $statusName = 'fail';
            }
        }

        if (isset($response['code'])) {
            if ($response['code'] > 299 || isset($response['body']['Errors'])) {
                $statusName = 'error';
            }
        }

        if (isset($statusName)) {
            return $this->configHelper->getCustomStatusConfig($statusName, $storeId);
        }
    }

    /**
     * Update Order Status From NoFraud Result
     *
     * @param mixed $noFraudOrderStatus
     * @param mixed $order
     * @param mixed $response
     * @param bool  $isCron
     */
    public function updateOrderStatusFromNoFraudResult($noFraudOrderStatus, $order, $response, bool $isCron = false)
    {
        if (!empty($noFraudOrderStatus)) {
            $newState = $this->getStateFromStatus($noFraudOrderStatus);
            if ($newState == Order::STATE_HOLDED) {
                $this->holdOrder($order);
            } elseif ($newState) {
                // Unhold order if it is currently on hold and new state is not hold
                $this->unholdOrder($order);

                $order->setStatus($noFraudOrderStatus)->setState($newState);
                $noFraudresponse = $response['http']['response']['body']['decision'] ?? "";
                if (isset($noFraudresponse) && ($noFraudresponse == 'pass')) {
                    $order->setNofraudStatus($noFraudresponse);
                    $order->save();
                    $this->nofraudInvoiceService->createInvoice($order, $isCron);
                }
            }

        }
    }

    /**
     * Get State From Status
     *
     * @param mixed $state
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
     * Handle Auto Cancel
     *
     * @param mixed $order
     * @param mixed $decision
     */
    public function handleAutoCancel($order, $decision)
    {
        // if order failed NoFraud check, try to refund and cancel order
        if ($decision == 'fail' || $decision == 'fraudulent') {
            $this->dataHelper->addDataToLog("Auto-canceling Order#" . $order->getIncrementId());
            // Handle custom cancel for Payment Method if needed
            if ($this->refundOrder($order) && !$this->_runCustomAutoCancel($order)) {
                $order->cancel();
                $order->setNofraudStatus($decision);
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED));
                $order->addStatusHistoryComment("NoFraud triggered order cancellation.");
                $order->save();

                return true;
            }
        }
        return false;
    }

    /**
     * Run Custom Auto Cancel
     *
     * @param mixed $order
     */
    private function _runCustomAutoCancel($order)
    {
        $isCustom = true;
        $method = $order->getPayment()->getMethod();

        switch ($method) {
            case (self::CYBERSOURCE_METHOD_CODE):
                $this->_handleCyberSourceAutoCanel($order);
                break;
            default:
                $isCustom = false;
                break;
        }
        return $isCustom;
    }

    /**
     * Handle Cyber Source Auto Canel
     *
     * @param mixed $order
     */
    private function _handleCyberSourceAutoCanel($order)
    {
        $this->orderManagement->cancel($order->getEntityId());
        $status = $this->orderStatusFactory->create()->loadDefaultByState(Order::STATE_CANCELED)->getStatus();
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus($status);
        $order->save();
    }

    /**
     * Refund Order
     *
     * @param mixed $order
     */
    public function refundOrder($order)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isOffline = $order->getPayment()->getMethodInstance()->isOffline();
        // If payment method is online, attempt online refund if enabled
        $this->dataHelper->addDataToLog("Refunding Order#" . $order->getIncrementId());
        $isRefundOnline = $this->configHelper->getRefundOnline($storeId);
        $this->dataHelper->addDataToLog("Refund Online: " . $isRefundOnline);
        if (isset($isOffline) && !$isOffline && $isRefundOnline) {
            $this->dataHelper->addDataToLog("Attempting Online Refund for Order#" . $order->getIncrementId());
            $invoices = $order->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                try {
                    $this->dataHelper->addDataToLog("Invoice can refund: " . $invoice->canRefund());
                    $this->dataHelper->addDataToLog("Invoice can void: " . $invoice->canVoid());
                    if ($invoice->canRefund()) {
                        $this->refundInvoiceInterface->execute($invoice->getId(), [], true);
                        $order->addStatusHistoryComment(
                            "NoFraud triggered refund of invoice {$invoice->getIncrementId()}"
                        )->save();
                    } elseif ($invoice->canVoid()) {
                        $invoice->void();
                        $order->addStatusHistoryComment(
                            "NoFraud triggered void of invoice {$invoice->getIncrementId()}"
                        )->save();
                    } else {
                        return false;
                    }
                } catch (\Exception $e) {
                    $this->logger->logRefundException($e, $order->getId());
                    $order->addStatusHistoryComment(
                        "NoFraud refund failed for invoice {$invoice->getIncrementId()}. " .
                        "Please consult the logs at var/log/info.log for more information."
                    )->save();
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Hold Order
     *
     * @param mixed $order
     */
    private function holdOrder($order)
    {
        if ($order->canHold()) {
            $this->dataHelper->addDataToLog("Order {$order->getIncrementId()} is able to be put on hold");
            try {
                $order->hold()->save();
                $this->dataHelper->addDataToLog("Order {$order->getIncrementId()} has been put on hold");
            } catch (\Exception $e) {
                $this->dataHelper->addDataToLog("Order {$order->getIncrementId()} could not be put on hold");
                $this->dataHelper->addDataToLog($e->getMessage());
            }
        }
    }

    /**
     * Unhold Order
     *
     * @param mixed $order
     */
    private function unholdOrder($order)
    {
        if ($order->canUnhold()) {
            $this->dataHelper->addDataToLog("Order {$order->getIncrementId()} is able to be taken off hold");
            try {
                $order->unhold()->save();
                $this->dataHelper->addDataToLog("Order {$order->getIncrementId()} has been taken off hold");
            } catch (\Exception $e) {
                $this->dataHelper->addDataToLog("Order {$order->getIncrementId()} could not be taken off hold");
                $this->dataHelper->addDataToLog($e->getMessage());
            }
        }
    }
}

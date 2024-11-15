<?php

namespace NoFraud\Connect\Observer;

class SalesOrderPaymentPlaceEnd implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    /**
     * @var RequestHandler
     */
    protected $requestHandler;
    /**
     * @var ResponseHandler
     */
    protected $responseHandler;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var ApiUrl
     */
    protected $apiUrl;
    /**
     * @var OrderProcessor
     */
    protected $orderProcessor;
    /**
     * @var NotesHelper
     */
    protected $notesHelper;
    /**
     * @var OrderStatusCollection
     */
    protected $orderStatusCollection;
    /**
     * @var StoreManager
     */
    protected $storeManager;
    /**
     * @var InvoiceService
     */
    protected $invoiceService;
    /**
     * @var CreditmemoFactory
     */
    protected $creditmemoFactory;
    /**
     * @var CreditmemoService
     */
    protected $creditmemoService;
    /**
     * @var Registry
     */
    protected $_registry;

    // Status Constants
    private const NOFRAUD_STATUS_SKIP = 'skip';

    // Payment Method Constants
    private const PAYMENT_METHOD_NOFRAUD = 'nofraud';

    // Logging Messages
    private const LOG_PAYMENT_IGNORED = "Payment method is ignored";
    private const LOG_NO_ORDER_FOUND = "No order found for payment";
    private const LOG_STATUS_IGNORED = "Order status is ignored";

    // Comments
    private const COMMENT_STATUS_IGNORED = "Order status is ignored";
    private const COMMENT_PAYMENT_IGNORED = "Payment method is ignored";
    private const COMMENT_CUSTOMER_GROUP_IGNORED = "Customer group is ignored";

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Helper\Config $configHelper
     * @param \NoFraud\Connect\Api\RequestHandler $requestHandler
     * @param \NoFraud\Connect\Api\ResponseHandler $responseHandler
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param \NoFraud\Connect\Api\ApiUrl $apiUrl
     * @param \NoFraud\Connect\Order\Processor $orderProcessor
     * @param \NoFraud\Connect\Helper\Notes $notesHelper
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory
     * @param \Magento\Sales\Model\Service\CreditmemoService $creditmemoService
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Api\RequestHandler $requestHandler,
        \NoFraud\Connect\Api\ResponseHandler $responseHandler,
        \NoFraud\Connect\Logger\Logger $logger,
        \NoFraud\Connect\Api\ApiUrl $apiUrl,
        \NoFraud\Connect\Order\Processor $orderProcessor,
        \NoFraud\Connect\Helper\Notes $notesHelper,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Framework\Registry $registry
    ) {
        $this->configHelper = $configHelper;
        $this->requestHandler = $requestHandler;
        $this->responseHandler = $responseHandler;
        $this->logger = $logger;
        $this->apiUrl = $apiUrl;
        $this->orderProcessor = $orderProcessor;
        $this->notesHelper = $notesHelper;
        $this->orderStatusCollection = $orderStatusCollection;
        $this->storeManager = $storeManager;
        $this->invoiceService = $invoiceService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->_registry = $registry;
    }

    /**
     * Sales Order Payment Place End event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // If module is disabled in admin config, do nothing
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->configHelper->getEnabled($storeId)) {
            return;
        }

        $payment = $observer->getEvent()->getPayment();

        if (!$payment) {
            return;
        }

        $order = $payment->getOrder();

        if ($this->shouldSkipOrderProcessing($order, $storeId) || !$this->isPaymentValid($order, $payment, $storeId)) {
            $this->markOrderAsSkipped($order);
            return;
        }

        // Update Payment with card values if payment method has stripped them
        //
        $payment = $this->_getPaymentDetailsFromMethod($payment);

        // If the payment has NOT been processed by a payment processor, AND
        // is NOT an offline payment method, then do nothing
        //
        // Some payment processors like Authorize.net may cause this Event to fire
        // multiple times, but the logic below this point should not be executed
        // We use the registry to keep track of the initial execution of the event
        //
        $afterOrderSaveNoFraudExecuted = $this->_registry->registry('afterOrderSaveNoFraudExecuted');
        $isOffline = $payment->getMethodInstance()->isOffline();
        if ($afterOrderSaveNoFraudExecuted && !$isOffline) {
            return;
        }
        // Register afterOrderSaveNoFraudExecuted on the first run to only allow transacions to be screened once
        $this->_registry->register('afterOrderSaveNoFraudExecuted', true);

        // Get NoFraud Api Token
        $apiToken = $this->configHelper->getApiToken($storeId);

        // Use the NoFraud Sandbox URL if Sandbox Mode is enabled in admin config
        $apiUrl = $this->apiUrl->whichEnvironmentUrl($storeId);

        // Build the NoFraud API request JSON from the payment and order objects
        $request = $this->requestHandler->build(
            $payment,
            $order,
            $apiToken
        );

        try {
            // Send the request to the NoFraud API and get response
            $resultMap = $this->requestHandler->send($request, $apiUrl);
            // Log request results with associated invoice number
            $this->logger->logTransactionResults($order, $payment, $resultMap);

            // Prepare order data from result map
            $data = $this->responseHandler->getTransactionData($resultMap);

            // For all API responses (official results from NoFraud, client errors, etc.),
            // add an informative comment to the order in Magento admin
            $comment = $data['comment'];
            if (!empty($comment)) {
                $order->addStatusHistoryComment($comment);
            }

            // Order has been screened
            $order->setNofraudScreened(true);
            $order->setNofraudTransactionId($data['id']);

            if (isset($resultMap['http']['response']['body'])) {
                $nofraudDecision = $resultMap['http']['response']['body']['decision'] ?? "";
                if (isset($nofraudDecision) && !empty($nofraudDecision)) {
                    $newStatus = $this->orderProcessor->getCustomOrderStatus($resultMap['http']['response'], $storeId);
                    if (!empty($newStatus)) {
                        $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $resultMap);
                    }
                } else {
                    $nofraudErrorDecision = $resultMap['http']['response']['body']['Errors'] ?? "";
                    if (isset($nofraudErrorDecision) && !empty($nofraudErrorDecision)) {
                        $newStatus = $this->orderProcessor->getCustomOrderStatus($resultMap['http']['response'], $storeId);
                        if (!empty($newStatus)) {
                            $order->setNofraudStatus($data['status']);
                            $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $resultMap);
                        }
                    }
                }
            }
            $order->setNofraudStatus($data['status']);
            $order->save();

            // If order fails screening and auto-cancel is enabled in admin config, cancel the order
            if ($this->configHelper->getAutoCancel($storeId) && isset($nofraudDecision) && $nofraudDecision == 'fail') {
                $this->orderProcessor->handleAutocancel($order, $nofraudDecision);
            }
        } catch (\Exception $exception) {
            $this->logger->logFailure($order, $exception);
        }
    }

    private function markOrderAsSkipped($order)
    {
        $this->_registry->register('afterOrderSaveNoFraudSkipRecorded', true);
        $order->setNofraudStatus(self::NOFRAUD_STATUS_SKIP);
        $order->save();
    }

    private function isPaymentValid($order, $payment, $storeId): bool
    {
        // If payment method is ignored, do nothing
        if ($this->configHelper->paymentMethodIsIgnored($payment->getMethod(), $storeId)) {
            $this->logger->logMessage(self::LOG_PAYMENT_IGNORED, $order);
            if (!$this->_registry->registry('afterOrderSaveNoFraudSkipRecorded')) {
                $this->notesHelper->addNoteToOrder($order, "Payment method is ignored");
            }
            return false;
        }

        // If transcation happen through nofraud checkout iframe, do nothing
        if ($payment->getMethod() == self::PAYMENT_METHOD_NOFRAUD) {
            return false;
        }

        return true;
    }

    private function shouldSkipOrderProcessing($order, $storeId)
    {
        // If order is not found, do nothing
        if (!$order) {
            $this->logger->logMessage(self::LOG_NO_ORDER_FOUND);
            return true;
        }

        $skipStatusCommentAdded = $this->_registry->registry('afterOrderSaveNoFraudSkipRecorded');

        // If order status is ignored, do nothing
        if ($this->configHelper->orderStatusIsIgnored($order, $storeId)) {
            $this->logger->logMessage(self::LOG_STATUS_IGNORED, $order);
            if (!$skipStatusCommentAdded) {
                $order->addStatusHistoryComment(self::COMMENT_STATUS_IGNORED);
            }
            return true;
        }

        // If customer group is ignored, do nothing
        $customerGroupId = $order->getCustomerGroupId();
        if ($this->configHelper->shouldSkipCustomerGroup($customerGroupId, $storeId)) {
            $this->logger->logMessage("Skipping as customer group '$customerGroupId' is ignored", $order);
            if (!$skipStatusCommentAdded) {
                $order->addStatusHistoryComment(self::COMMENT_CUSTOMER_GROUP_IGNORED);
            }
            return true;
        }

        return false;
    }

    /**
     * Get Payment Details From Method
     *
     * @param mixed $payment
     */
    private function _getPaymentDetailsFromMethod($payment)
    {
        $method = $payment->getMethod();

        if (strpos($method, "stripe_") === 0) {
            $payment = $this->_getPaymentDetailsFromStripe($payment);
        }

        return $payment;
    }

    /**
     * Get Payment Details From Stripe
     *
     * @param mixed $payment
     */
    private function _getPaymentDetailsFromStripe($payment)
    {
        if (empty($payment)) {
            return $payment;
        }

        $token = $payment->getAdditionalInformation('token');

        if (empty($token)) {
            $token = $payment->getAdditionalInformation('stripejs_token');
        }
        if (empty($token)) {
            $token = $payment->getAdditionalInformation('source_id');
        }
        if (empty($token)) {
            return $payment;
        }
        try {
            // Used by card payments
            if (strpos($token, "pm_") === 0) {
                $object = \Stripe\PaymentMethod::retrieve($token);
            } else {
                return $payment;
            }
            if (empty($object->customer)) {
                return $payment;
            }
        } catch (\Exception $e) {
            return $payment;
        }

        $cardData = $object->getLastResponse()->json['card'];

        $payment->setCcType($cardData['brand']);
        $payment->setCcExpMonth($cardData['exp_month']);
        $payment->setCcExpYear($cardData['exp_year']);
        $payment->setCcLast4($cardData['last4']);

        return $payment;
    }
}

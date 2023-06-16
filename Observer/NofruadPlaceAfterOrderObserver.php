<?php
/**
 * This file is an observer class for the NoFraud Connect extension that watches
 * for the nofraud_order_place_after event which is fired by the NoFraud Checkout
 * Module after an order is placed.
 *
 * @category Observer
 * @package  NoFraud_Connect
 * @link     https://nofraud.com
 */
namespace NoFraud\Connect\Observer;
/**
 * NofruadPlaceAfterOrderObserver class for NoFraud Connect extension.
 *
 * @category Class
 * @package  NoFraud_Connect
 * @link     https://nofraud.com
 */
class NofruadPlaceAfterOrderObserver implements \Magento\Framework\Event\ObserverInterface
{
    protected $configHelper;
    protected $requestHandler;
    protected $responseHandler;
    protected $logger;
    protected $apiUrl;
    protected $orderProcessor;
    protected $orderStatusCollection;
    protected $storeManager;
    protected $invoiceService;
    protected $creditmemoFactory;
    protected $creditmemoService;
    protected $_registry;

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Helper\Config                                    $configHelper          Config Helper Object from module
     * @param \NoFraud\Connect\Api\RequestHandler                               $requestHandler        Request Handler Object from module
     * @param \NoFraud\Connect\Api\ResponseHandler                              $responseHandler       Response Handler Object from module
     * @param \NoFraud\Connect\Logger\Logger                                    $logger                Logger Object from module
     * @param \NoFraud\Connect\Api\ApiUrl                                       $apiUrl                ApiUrl Object from module
     * @param \NoFraud\Connect\Order\Processor                                  $orderProcessor        Order Processor Object from module
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection Order Status Collection Object from Magento
     * @param \Magento\Store\Model\StoreManagerInterface                        $storeManager          Store Manager Object from Magento
     * @param \Magento\Sales\Model\Service\InvoiceService                       $invoiceService        Invoice Service Object from Magento
     * @param \Magento\Sales\Model\Order\CreditmemoFactory                      $creditmemoFactory     Creditmemo Factory Object from Magento
     * @param \Magento\Sales\Model\Service\CreditmemoService                    $creditmemoService     Creditmemo Service Object from Magento
     * @param \Magento\Framework\Registry                                       $registry              Registry Object from Magento
     */
    public function __construct(
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Api\RequestHandler $requestHandler,
        \NoFraud\Connect\Api\ResponseHandler $responseHandler,
        \NoFraud\Connect\Logger\Logger $logger,
        \NoFraud\Connect\Api\ApiUrl $apiUrl,
        \NoFraud\Connect\Order\Processor $orderProcessor,
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
        $this->orderStatusCollection = $orderStatusCollection;
        $this->storeManager = $storeManager;
        $this->invoiceService = $invoiceService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->_registry = $registry;
    }

    /**
     * Place After Order event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer Observer Object from Magento
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // If module is disabled in admin config, do nothing
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->configHelper->getEnabled($storeId)) {
            return;
        }

        $order = $observer->getEvent()->getOrder();

        // If payment method is blacklisted in the admin config, do nothing
        $payment = $order->getPayment();

        // If transcation happen through nofraud checkout iframe, do nothing
        if ($order && $payment->getMethod() == 'nofraud') {
            return;
        }

        if ($this->configHelper->paymentMethodIsIgnored($payment->getMethod(), $storeId)) {
            return;
        }

        // If order's status is ignored in admin config, do nothing
        /*if ($this->configHelper->orderStatusIsIgnored($order, $storeId)) {
            return;
        }*/

        // Update Payment with card values if payment method has stripped them
        $payment = $this->_getPaymentDetailsFromMethod($payment);
        // If the payment has NOT been processed by a payment processor, AND
        // is NOT an offline payment method, then do nothing
        //
        // Some payment processors like Authorize.net may cause this Event to fire
        // multiple times, but the logic below this point should not be executed
        // We use the registry to keep track of the initial execution of the event
        //
        $noFraudafterOrderSaveNoFraudExecuted = $this->_registry->registry('afterOrderSaveNoFraudExecuted');
        $isOffline = $payment->getMethodInstance()->isOffline();
        if ($noFraudafterOrderSaveNoFraudExecuted && !$isOffline) {
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
            $order->setNofraudStatus($data['status']);
            $order->setNofraudTransactionId($data['id']);

            if (isset($resultMap['http']['response']['body'])) {
                $nofraudDecision = $resultMap['http']['response']['body']['decision'];
                if ($nofraudDecision != 'fail' || $nofraudDecision != "fraudulent") {
                    $newStatus = $this->orderProcessor->getCustomOrderStatus($resultMap['http']['response'], $storeId);
                    $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $resultMap);
                }
            }
            // Finally, save order
            $order->save();
        } catch (\Exception $exception) {
            $this->logger->logFailure($order, $exception);
        }
    }

    /**
     * Get Payment Details From Method
     *
     * @param mixed $payment Payment Object from Magento
     *
     * @return mixed
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
     * @param mixed $payment Payment Object from Magento
     *
     * @return mixed
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

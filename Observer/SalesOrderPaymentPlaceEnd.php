<?php

namespace NoFraud\Connect\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Api\RequestHandler;
use NoFraud\Connect\Api\ResponseHandler;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Logger\Logger;
use NoFraud\Connect\Order\Processor;
use Stripe\PaymentMethod;

/**
 * Send Order details to the NF server after Order have been placed
 */
class SalesOrderPaymentPlaceEnd implements ObserverInterface
{
    /**
     * @var Config
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
     * @var Processor
     */
    protected $orderProcessor;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @param Config $configHelper
     * @param RequestHandler $requestHandler
     * @param ResponseHandler $responseHandler
     * @param Logger $logger
     * @param ApiUrl $apiUrl
     * @param Processor $orderProcessor
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     */
    public function __construct(
        Config $configHelper,
        RequestHandler $requestHandler,
        ResponseHandler $responseHandler,
        Logger $logger,
        ApiUrl $apiUrl,
        Processor $orderProcessor,
        StoreManagerInterface $storeManager,
        Registry $registry
    ) {
        $this->configHelper = $configHelper;
        $this->requestHandler = $requestHandler;
        $this->responseHandler = $responseHandler;
        $this->logger = $logger;
        $this->apiUrl = $apiUrl;
        $this->orderProcessor = $orderProcessor;
        $this->storeManager = $storeManager;
        $this->_registry = $registry;
    }

    /**
     * @param $resultMap
     * @param $order
     * @param $storeId
     */
    private function processOrder($resultMap, $order, $storeId)
    {
        try {
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

            // If auto-cancel is enabled, try to refund order if order failed NoFraud check
            if ($this->configHelper->getAutoCancel($storeId) &&
                isset($resultMap['http']['response']['body']['decision'])) {
                $this->orderProcessor->handleAutoCancel($order, $resultMap['http']['response']['body']['decision']);
            } else {
                // For official results from from NoFraud, update the order status
                // according to admin config preferences
                if (isset($resultMap['http']['response']['body'])) {
                    $newStatus = $this->orderProcessor->getCustomOrderStatus($resultMap['http']['response'], $storeId);
                    // Update state and status
                    $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order);
                }
            }

            // Finally, save order
            $order->save();
        } catch (Exception $exception) {
            $this->logger->logFailure($order, $exception);
        }
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // If module is disabled in admin config, do nothing
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->configHelper->getEnabled($storeId)) {
            return;
        }

        // If payment method is blacklisted in the admin config, do nothing
        $payment = $observer->getEvent()->getPayment();
        if ($this->configHelper->paymentMethodIsIgnored($payment->getMethod(), $storeId)) {
            return;
        }

        // If order's status is ignored in admin config, do nothing
        $order = $payment->getOrder();
        if ($this->configHelper->orderStatusIsIgnored($order, $storeId)) {
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
        if ($this->_registry->registry('afterOrderSaveNoFraudExecuted') &&
            !$payment->getMethodInstance()->isOffline()) {
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

        // Send the request to the NoFraud API and get response
        $resultMap = $this->requestHandler->send($request, $apiUrl);

        // Log request results with associated invoice number
        $this->logger->logTransactionResults($order, $payment, $resultMap);

        $this->processOrder($resultMap, $order, $storeId);
    }

    /**
     * @param $payment
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
     * @param $payment
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
                $object = PaymentMethod::retrieve($token);
            } else {
                return $payment;
            }

            if (empty($object->customer)) {
                return $payment;
            }
        } catch (Exception $e) {
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

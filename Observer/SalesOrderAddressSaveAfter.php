<?php

namespace NoFraud\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Api\RequestHandler;
use NoFraud\Connect\Api\ResponseHandler;
use NoFraud\Connect\Logger\Logger;
use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Order\Processor;

class SalesOrderAddressSaveAfter implements ObserverInterface
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
     * Constructor
     *
     * @param Config $configHelper
     * @param RequestHandler $requestHandler
     * @param ResponseHandler $responseHandler
     * @param Logger $logger
     * @param ApiUrl $apiUrl
     * @param Processor $orderProcessor
     */
    public function __construct(
        Config          $configHelper,
        RequestHandler  $requestHandler,
        ResponseHandler $responseHandler,
        Logger          $logger,
        ApiUrl          $apiUrl,
        Processor       $orderProcessor
    ) {
        $this->configHelper    = $configHelper;
        $this->requestHandler  = $requestHandler;
        $this->responseHandler = $responseHandler;
        $this->logger          = $logger;
        $this->apiUrl          = $apiUrl;
        $this->orderProcessor  = $orderProcessor;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $address = $observer->getEvent()->getAddress();
        $order   = $address->getOrder();
        $payment = $order->getPayment();
        $method  = $payment->getMethodInstance();
        $storeId = $order->getStoreId();

        if (!$this->configHelper->getEnabled($storeId)) {
            return;
        }

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
                    if ($nofraudDecision != 'fail' || $nofraudDecision != "fraudulent") {
                        $newStatus = $this->orderProcessor->getCustomOrderStatus($resultMap['http']['response'], $storeId);
                        if (!empty($newStatus)) {
                            $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $resultMap);
                        }
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
            // Finally, save order
            $order->setNofraudStatus($data['status']);
            $nofraudDecision = $resultMap['http']['response']['body']['decision'] ?? "";

            if ($this->configHelper->getAutoCancel($storeId)) {
                if (isset($nofraudDecision) && $nofraudDecision == 'fail') {
                    $this->orderProcessor->handleAutocancel($order, $nofraudDecision);
                }
            } else {
                if (isset($nofraudDecision) && $nofraudDecision == 'fail') {
                    $order->setNofraudStatus($nofraudDecision);
                }
            }
            $order->addStatusHistoryComment(__('NoFraud updated order status to ' . $data['status']), false);
            $order->save();
        } catch (\Exception $exception) {
            $this->logger->logFailure($order, $exception);
        }
    }
}

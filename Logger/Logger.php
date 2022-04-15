<?php
namespace NoFraud\Connect\Logger;

/**
 * Provides Logging functionality for NF
 */
class Logger extends \Monolog\Logger
{
    /**
     * Add log Transaction Results
     * @param $order
     * @param $payment
     * @param $resultMap
     */
    public function logTransactionResults($order, $payment, $resultMap)
    {
        $orderLog['id'] = $order->getIncrementId();

        $paymentLog['method'] = $payment->getMethod();

        $info = [
            'order' => $orderLog,
            'payment' => $paymentLog,
            'api_result' => $resultMap,
        ];

        $this->info(json_encode($info));
    }

    /**
     * Add log Cancel Transaction Results
     * @param $order
     * @param $resultMap
     */
    public function logCancelTransactionResults($order, $resultMap)
    {
        $orderLog['id'] = $order->getIncrementId();

        $info = [
            'order' => $orderLog,
            'api_result' => $resultMap,
        ];

        $this->info(json_encode($info));
    }

    /**
     * @param $order
     * @param $exception
     * @return void
     */
    public function logFailure($order, $exception)
    {
        $orderId = $order->getIncrementId();
        $this->critical("Encountered an exception while processing Order {$orderId}: \n" . (string) $exception);
    }

    /**
     * @param null $params
     * @param $apiUrl
     * @param $curlError
     * @param $responseCode
     * @return void
     */
    public function logApiError($apiUrl, $curlError, $responseCode, $params = null)
    {
        $this->critical("Encountered an exception while sending an API request. Here is the API url: {$apiUrl}");
        $this->critical("Encountered an exception while sending an API request. Here are the parameters: ");
        $this->critical(print_r($params, true));
        $this->critical("Encountered an exception while sending an API request. Here is the response code: ");
        $this->critical(print_r($responseCode, true));
        $this->critical("Encountered an exception while sending an API request. Here is the exception: ");
        $this->critical(print_r($curlError, true));
    }

    /**
     * @param $exception
     * @param $orderNumber
     * @return void
     */
    public function logRefundException($exception, $orderNumber)
    {
        $this->critical('We could not process the refund for order number ' .
            $orderNumber . ' for the following reasons:');
        $this->critical($exception->getMessage());
    }
}

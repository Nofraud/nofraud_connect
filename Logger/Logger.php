<?php

namespace NoFraud\Connect\Logger;

class Logger extends \Monolog\Logger
{
    /**
     * Log Transaction Results
     *
     * @param mixed $order
     * @param mixed $payment
     * @param mixed $resultMap
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
     * Log Cancel Transaction Results
     *
     * @param mixed $order
     * @param mixed $resultMap
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
     * Log Failure
     *
     * @param mixed $order
     * @param mixed $exception
     */
    public function logFailure($order, $exception)
    {
        $orderId = $order->getIncrementId();
        $this->critical("Encountered an exception while processing Order {$orderId}: \n" . (string) $exception);
    }

    /**
     * Log Api Error
     *
     * @param mixed $apiUrl
     * @param mixed $curlError
     * @param mixed $responseCode
     * @param mixed $params
     */
    public function logApiError($apiUrl, $curlError, $responseCode, $params = null)
    {
        $this->critical("Encountered an exception while sending an API request. Here is the API url: {$apiUrl}");
        $this->critical("Encountered an exception while sending an API request. Here are the parameters: ");
        $this->critical($params);
        $this->critical("Encountered an exception while sending an API request. Here is the response code: ");
        $this->critical($responseCode);
        $this->critical("Encountered an exception while sending an API request. Here is the exception: ");
        $this->critical($curlError);
    }

    /**
     * Log Refund Exception
     *
     * @param mixed $exception
     * @param mixed $orderNumber
     */
    public function logRefundException($exception, $orderNumber)
    {
        $this->critical('We could not process the refund for order number' . $orderNumber . 'for the following reasons:');
        $this->critical($exception->getMessage());
    }

    public function logMessage($message, $order): void
    {
        if (!$order) {
            $orderId = $order->getIncrementId();
        } else {
            $orderId = 'N/A';
        }

        $this->info("Order $orderId: $message");
    }
}

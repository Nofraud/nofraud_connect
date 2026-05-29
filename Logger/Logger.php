<?php

namespace NoFraud\Connect\Logger;

use Magento\Framework\Exception\CommandException;

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
        $info = [
            'order_id' => $order->getIncrementId(),
            'payment_method' => $payment->getMethod(),
            'decision' => $resultMap['http']['response']['body']['decision'] ?? 'unknown',
            'transaction_id' => $resultMap['http']['response']['body']['id'] ?? null,
            'response_code' => $resultMap['http']['response']['code'] ?? null,
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
        $info = [
            'order_id' => $order->getIncrementId(),
            'transaction_id' => $resultMap['http']['response']['body']['id'] ?? null,
            'response_code' => $resultMap['http']['response']['code'] ?? null,
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
        $this->critical("Encountered an exception while processing Order {$orderId}: " . $exception->getMessage());
    }

    /**
     * Log Api Error
     *
     * @param string $apiUrl
     * @param string $curlError
     * @param int|string $responseCode
     */
    public function logApiError($apiUrl, $curlError, $responseCode)
    {
        $this->critical("API request exception — URL: {$apiUrl}, response code: {$responseCode}, error: {$curlError}");
    }

    /**
     * Log Refund Exception
     *
     * @param mixed $exception
     * @param mixed $orderNumber
     */
    public function logRefundException($exception, $orderNumber)
    {
        $this->critical(
            'We could not process the refund for order number ' . $orderNumber . ' for the following reasons:'
        );

        if ($exception instanceof CommandException) {
            $this->critical($exception->getRawMessage());
        } else {
            $this->critical($exception->getMessage());
        }
    }
}

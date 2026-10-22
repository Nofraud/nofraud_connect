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
        $apiUrl = $this->maskApiToken($apiUrl);
        $this->critical("Encountered an exception while sending an API request. Here is the API url: {$apiUrl}");
        if ($params !== null) {
            unset($params['nf-token'], $params['nf_token']);
            $this->critical("Encountered an exception while sending an API request. Here are the parameters: ");
            $this->critical((string) json_encode($params));
        }
        $this->critical("Encountered an exception while sending an API request. Here is the response code: ");
        $this->critical((string) $responseCode);
        $this->critical("Encountered an exception while sending an API request. Here is the exception: ");
        $this->critical($this->maskApiToken($curlError));
    }

    /**
     * Mask the API token in NoFraud status URLs, e.g. status/{token}/{order}
     *
     * @param mixed $text
     * @return string
     */
    public function maskApiToken($text)
    {
        return preg_replace('#(/status(?:_by_invoice|_by_url)?/)[^/?\s]+(?=/)#', '$1****', (string) $text);
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

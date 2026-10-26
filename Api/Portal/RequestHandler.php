<?php

namespace NoFraud\Connect\Api\Portal;

class RequestHandler extends \NoFraud\Connect\Api\Request\Handler\AbstractHandler
{
    private const TRANSACTION_STATUS_ENDPOINT = 'status_by_invoice';

    /**
     * Build
     *
     * @param string $apiUrl | Nofraud API Url
     * @param string $orderId | Order Id
     * @param string $apiToken | NoFraud API Token
     *
     * @return array|bool
     */
    public function build($apiUrl, $orderId, $apiToken)
    {
        $params = [];
        $params['nf_token'] = $apiToken;
        $params['transaction_id'] = $this->getTransactionIdFromNoFraud($apiUrl, $orderId, $apiToken);

        if (!$params['transaction_id']) {
            return false;
        }

        return $this->scrubEmptyValues($params);
    }

    /**
     * Get Transaction StatusUrl
     *
     * @param mixed $apiUrl
     * @param mixed $orderId
     * @param mixed $apiToken
     * @return void
     */
    protected function getTransactionStatusUrl($apiUrl, $orderId, $apiToken)
    {
        // The API route is status_by_invoice/{token}/{invoice}
        return $apiUrl . self::TRANSACTION_STATUS_ENDPOINT . '/' . $apiToken . '/' . $orderId;
    }

    /**
     * Get Transaction Id From NoFraud
     *
     * @param mixed $apiUrl
     * @param mixed $orderId
     * @param mixed $apiToken
     * @return void
     */
    protected function getTransactionIdFromNoFraud($apiUrl, $orderId, $apiToken)
    {
        $params = [];
        $response = $this->send($params, $this->getTransactionStatusUrl($apiUrl, $orderId, $apiToken), 'GET');

        if (isset($response['http']['response']['body']['id'])) {
            return $response['http']['response']['body']['id'];
        }
        return false;
    }
}

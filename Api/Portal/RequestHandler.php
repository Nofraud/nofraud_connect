<?php
 
namespace NoFraud\Connect\Api\Portal;
 
use NoFraud\Connect\Api\Request\Handler\AbstractHandler;
use NoFraud\Connect\Logger\Logger;
use org\bovigo\vfs\DirectoryIterationTestCase;

class RequestHandler extends AbstractHandler
{
    public const TRANSACTION_STATUS_ENDPOINT = 'status_by_invoice';

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Build params request
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
     * Get Transaction Status Url
     * @param $apiUrl
     * @param $orderId
     * @param $apiToken
     * @return string
     */
    protected function getTransactionStatusUrl($apiUrl, $orderId, $apiToken): string
    {
        return $apiUrl . self::TRANSACTION_STATUS_ENDPOINT . DIRECTORY_SEPARATOR . $apiToken .
            DIRECTORY_SEPARATOR . $orderId;
    }

    /**
     * @param $apiUrl
     * @param $orderId
     * @param $apiToken
     * @return false|mixed
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

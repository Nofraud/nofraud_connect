<?php
 
namespace NoFraud\Connect\Api\Portal;

use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Logger\Logger;

/**
 * Build Api Url
 */
class ApiUrl
{
    const PORTAL_URL = 'https://portal-api.nofraud.com/';
    const CANCEL_ORDER_ENDPOINT = 'api/v1/transaction-update/cancel-transaction';

    protected $configHelper;
    protected $logger;

    /**
     * @param Config $configHelper
     * @param Logger $logger
     */
    public function __construct(
        Config $configHelper,
        Logger $logger
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * Build Order Api Url
     * @param string $orderInfoRequest | Info wanted from order e.x. 'status'
     * @param string $apiToken | API Token
     * @return string
     */
    public function buildOrderApiUrl(string $orderInfoRequest, $apiToken): string
    {
        $apiBaseUrl = $this->getPortalUrl();
        return $apiBaseUrl . $orderInfoRequest . '/' . $apiToken;
    }

    /**
     * Get Cancel url
     * @return string
     */
    public function getPortalOrderCancelUrl(): string
    {
        return $this->getPortalUrl() . self::CANCEL_ORDER_ENDPOINT;
    }

    /**
     * Get portal url
     * @return string
     */
    protected function getPortalUrl(): string
    {
        return self::PORTAL_URL;
    }
}

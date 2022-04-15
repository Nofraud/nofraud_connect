<?php
 
namespace NoFraud\Connect\Api;
 
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Logger\Logger;

class ApiUrl
{
    const PRODUCTION_URL = 'https://api.nofraud.com/';
    const SANDBOX_URL    = 'https://apitest.nofraud.com/';

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
     * Build order apiUrl
     * @param string $orderInfoRequest | Info wanted from order e.x. 'status'
     * @param string $apiToken | API Token
     */
    public function buildOrderApiUrl(string $orderInfoRequest, $apiToken): string
    {
        $apiBaseUrl = $this->whichEnvironmentUrl();
        return $apiBaseUrl . $orderInfoRequest . '/' . $apiToken;
    }

    /**
     * Get which environment url
     * @param null $storeId
     * @return string
     */
    public function whichEnvironmentUrl($storeId = null): string
    {
        return $this->configHelper->getSandboxMode($storeId) ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Get Production Url
     * @return string
     */
    public function getProductionUrl(): string
    {
        return self::PRODUCTION_URL;
    }
}

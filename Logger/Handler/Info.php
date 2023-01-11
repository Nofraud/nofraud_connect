<?php
namespace NoFraud\Connect\Logger\Handler;

class Info extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO;

    /**
     * @var string
     */
    protected $fileName = '/var/log/nofraud_connect/info.log';
}

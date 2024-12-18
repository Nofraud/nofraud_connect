<?php
namespace NoFraud\Connect\Logger\Handler;

class Error extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = \Monolog\Logger::ERROR;

    /**
     * Default log file path (static file name).
     * @var string
     */
    protected $fileName = '/var/log/nofraud_connect/info.log';

    /**
     * Filesystem driver for writing logs.
     * 
     * @var DriverInterface
     */
    protected $filesystemDriver;

    public function __construct(
        DriverInterface $filesystemDriver
    ) {
        $this->filesystemDriver = $filesystemDriver;
        parent::__construct($filesystemDriver);
    }

    /**
     * Override write() to use dynamic date logic for log file.
     * 
     * @param array $record
     */
    protected function write(array $record): void
    {
        $dynamicFileName = '/var/log/nofraud_connect/' . date("d-m-Y") . '.log';

        // Ensure the directory exists
        $directory = dirname($dynamicFileName);
        if (!$this->filesystemDriver->isDirectory($directory)) {
            $this->filesystemDriver->createDirectory($directory, 0755);
        }

        // Write to the dynamically resolved log file
        $this->filesystemDriver->filePutContents(
            $dynamicFileName,
            $record['formatted'],
            FILE_APPEND
        );
    }
}

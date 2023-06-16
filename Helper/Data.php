<?php

namespace NoFraud\Connect\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    private const XML_PATH_ORDER_DEBUG_ENABLED = "nofraud_connect/order_debug/debug";

    /**
     * @var \Magento\Framework\Filesystem\Directory\Write
     */
    protected $_directory;
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /** @var File */
    private $file;
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var WriteInterface
     */
    protected $logDirectory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     * @param ObjectManagerInterface $objectManager
     * @param File $file
     * @param Filesystem $filesystem
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        ObjectManagerInterface $objectManager,
        File $file,
        Filesystem $filesystem
    ) {
        $this->directoryList = $directoryList;
        $this->objectManager = $objectManager;
        $this->file = $file;
        $this->_directory = $filesystem->getDirectoryWrite(
            DirectoryList::VAR_DIR
        );
        parent::__construct($context);
    }
    /**
     * Get Debug Mode is Enabled?
     *
     * @return boolean
     */
    public function getDebugModeIsEnabled()
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ORDER_DEBUG_ENABLED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    /**
     * Log Data if enabled
     *
     * @param mixed $data
     */
    public function addDataToLog($data)
    {
        if (!$this->getDebugModeIsEnabled()) {
            return;
        }
        $baseVarDir = $this->directoryList->getPath("var");
        if (!$this->_directory->isDirectory("log")) {
            $this->file->mkdir($baseVarDir . "/log", 0777);
        }
        if (!$this->_directory->isDirectory("log/nofraud_connect")) {
            $this->file->mkdir($baseVarDir . "/log/nofraud_connect", 0777);
        }
        $productMetadata = $this->objectManager->get(
            ProductMetadataInterface::class
        );
        $version = $productMetadata->getVersion();
        if (version_compare($version, "2.4.3", "<")) {
            $writer = new \Laminas\Log\Writer\Stream(
                $baseVarDir .
                    "/log/nofraud_connect/payment-" .
                    date("d-m-Y") .
                    ".log"
            );
            $logger = new \Laminas\Log\Logger();
            $logger->addWriter($writer);
        } else {
            $writer = new \Zend_Log_Writer_Stream(
                $baseVarDir .
                    "/log/nofraud_connect/payment-" .
                    date("d-m-Y") .
                    ".log"
            );
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
        }
        if ($data && is_array($data)) {
            $logger->info(print_r($data,true));
        } else {
            $logger->info($data);
        }
    }
}

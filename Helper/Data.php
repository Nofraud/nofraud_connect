<?php

namespace NoFraud\Connect\Helper;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
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
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     *
     * @var CollectionFactory
     */
    protected $statusCollectionFactory;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     * @param ObjectManagerInterface $objectManager
     * @param File $file
     * @param Filesystem $filesystem
     * @param CollectionFactory $statusCollectionFactory
     * @param Config $configHelper
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        ObjectManagerInterface $objectManager,
        File $file,
        Filesystem $filesystem,
        CollectionFactory $statusCollectionFactory,
        Config $configHelper
    ) {
        $this->directoryList = $directoryList;
        $this->objectManager = $objectManager;
        $this->file = $file;
        $this->_directory = $filesystem->getDirectoryWrite(
            DirectoryList::VAR_DIR
        );
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->configHelper = $configHelper;
        parent::__construct($context);
    }
    /**
     * Log Data if enabled
     *
     * @param mixed $data
     */
    public function addDataToLog($data)
    {
        if (!$this->configHelper->isDebugLoggingAllowed()) {
            return;
        }

        $logger = $this->getLogger();

        if ($data && is_array($data)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $logger->info(print_r($data, true));
        } else {
            $logger->info($data);
        }
    }

    /**
     * Log Error
     *
     * @param mixed $data
     * @return void
     */
    public function addErrorToLog($data)
    {
        $logger = $this->getLogger();

        if ($data && is_array($data)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $logger->err(print_r($data, true));
        } else {
            $logger->err($data);
        }
    }

    /**
     * Log Debug
     *
     * @param mixed $data
     * @return void
     */
    public function addDebugToLog($data)
    {
        if (!$this->configHelper->isDebugLoggingAllowed()) {
            return;
        }

        $logger = $this->getLogger();

        if ($data && is_array($data)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $logger->info(print_r($data, true));
        } else {
            $logger->info($data);
        }
    }

    /**
     * Get Logger
     *
     * @return \Laminas\Log\Logger|\Zend_Log
     */
    private function getLogger()
    {
        $baseVarDir = $this->directoryList->getPath("var");
        if (!$this->_directory->isDirectory("log")) {
            $this->file->mkdir($baseVarDir . "/log", 0750);
        }
        if (!$this->_directory->isDirectory("log/nofraud_connect")) {
            $this->file->mkdir($baseVarDir . "/log/nofraud_connect", 0750);
        }
        $htaccessPath = $baseVarDir . "/log/nofraud_connect/.htaccess";
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Apache 2.4\n<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n</IfModule>\n"
                . "# Apache 2.2\n<IfModule !mod_authz_core.c>\n"
                . "    Deny from all\n</IfModule>\n";
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            file_put_contents($htaccessPath, $htaccessContent);
        }
        $productMetadata = $this->objectManager->get(
            ProductMetadataInterface::class
        );
        $logFile = $baseVarDir . "/log/nofraud_connect/log-" . date("d-m-Y") . ".log";
        $version = $productMetadata->getVersion();
        if (version_compare($version, "2.4.3", "<")) {
            $writer = new \Laminas\Log\Writer\Stream($logFile, null, 0640);
            $logger = new \Laminas\Log\Logger();
            $logger->addWriter($writer);
        } else {
            $writer = new \Zend_Log_Writer_Stream($logFile, null, 0640);
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
        }

        return $logger;
    }

    /**
     * Retrieve the label for a given order status code.
     *
     * @param string $statusCode
     * @return string
     */
    public function getStatusLabelByCode($statusCode)
    {
        // Retrieve the collection of statuses and build a lookup array
        $statusLabels = [];
        $statuses = $this->statusCollectionFactory->create();
        
        foreach ($statuses as $status) {
            $statusLabels[$status->getStatus()] = $status->getLabel();
        }

        // Return the label based on the provided status code, or 'Unknown Status' if not found
        return $statusLabels[$statusCode] ?? 'Unknown Status';
    }
}

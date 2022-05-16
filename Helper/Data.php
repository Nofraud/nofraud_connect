<?php
namespace NoFraud\Connect\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	const XML_PATH_ORDER_DEBUG_ENABLED = "nofraud_connect/order_debug/debug";

	/**
     * @type ObjectManagerInterface
     */
    protected $objectManager;

	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Magento\Framework\Filesystem\DirectoryList $directoryList,
		ObjectManagerInterface $objectManager
	) {
		$this->directoryList = $directoryList;
		$this->objectManager = $objectManager;
		parent::__construct($context);
	}

	/**
	 * Get Debug Mode is Enabled?
	 *
	 * @return boolean
	 */
	public function getDebugModeIsEnabled() {
		return $this->scopeConfig->getValue(
			self::XML_PATH_ORDER_DEBUG_ENABLED,
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
	}

	/**
	 * Log Data if enabled
	 */
	public function addDataToLog($data) {
		if(!$this->getDebugModeIsEnabled()) {
			return;
		}
		$baseVarDir = $this->directoryList->getPath("var");
		if(!is_dir($baseVarDir."/log")) {
			mkdir($baseVarDir."/log", 0777);
		}
		if(!is_dir($baseVarDir."/log/nofraud_connect")) {
			mkdir($baseVarDir."/log/nofraud_connect", 0777);
		}

		$productMetadata = $this->objectManager->get(ProductMetadataInterface::class);

		$version = $productMetadata->getVersion();
		
		if(version_compare($version, '2.4.3', '<')) {
			$writer = new \Laminas\Log\Writer\Stream($baseVarDir . '/log/nofraud_connect/payment-'.date("d-m-Y").'.log');
			$logger = new  \Laminas\Log\Logger();
			$logger->addWriter($writer);
			$logger->info(print_r($data, true));
		} else {
			$writer = new \Zend_Log_Writer_Stream($baseVarDir . '/log/nofraud_connect/payment-'.date("d-m-Y").'.log');
			$logger = new \Zend_Log();
			$logger->addWriter($writer);
			$logger->info(print_r($data, true));
		}
	}
}

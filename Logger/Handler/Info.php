<?php
namespace NoFraud\Connect\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;

class Info extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO;

    /**
     * File name
     * @var string
     */
    public $fileName = '';
    /**
     * File name
     * @var string
     */
    public $cutomfileName = 'NO_PATH';
    /**
     * @var TimezoneInterface
     */
    protected $_localeDate;

    public function __construct(
        DriverInterface $filesystem,
        \Magento\Framework\Filesystem $corefilesystem,
        $filePath = null
    ) {
        $corefilesystem = $corefilesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $logpath = $corefilesystem->getAbsolutePath('log/');

        $filename = 'nofraud_connect/' . date("m-d-Y") . '.log';
        $filepath = $logpath . $filename;
        $this->cutomfileName = $filepath;
        parent::__construct(
            $filesystem,
            $filepath
        );
    }
}

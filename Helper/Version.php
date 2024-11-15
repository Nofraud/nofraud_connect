<?php
/**
 * Created by Nofraud Connect
 * Author: Soleil Cotterell
 * Date: 8/23/24
 */

namespace NoFraud\Connect\Helper;

use Magento\Framework\Module\Dir\Reader as DirReader;

class Version extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $dirReader;

    /**
     * Constructor
     *
     * @param DirReader $dirReader
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        DirReader $dirReader,
        \Magento\Framework\App\Helper\Context $context,
    ) {
        parent::__construct($context);
        $this->dirReader = $dirReader;
    }

    public function getVersion()
    {
        $installVersion = "unidentified";
        $composer = $this->getComposerInformation("NoFraud_Connect");

        if ($composer) {
            $installVersion = $composer['version'];
        }

        return $installVersion;
    }

    public function getComposerInformation($moduleName)
    {
        $dir = $this->dirReader->getModuleDir("", $moduleName);

        if (file_exists($dir . '/composer.json')) {
            return json_decode(file_get_contents($dir . '/composer.json'), true);
        }

        return false;
    }
}

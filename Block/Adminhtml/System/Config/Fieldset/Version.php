<?php
/**
 * Created by Nofraud Connect
 * Author: Sam Umaretiya
 * Date: 18/01/2023
 * Time: 9:41
 */

namespace NoFraud\Connect\Block\Adminhtml\System\Config\Fieldset;

use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Backend\Block\Template;
use Magento\Framework\Module\Dir\Reader as DirReader;

class Version extends Template implements RendererInterface
{
    /** @var DirReader */
    protected $dirReader;

    /**
     * Constructor
     *
     * @param DirReader $dirReader
     * @param Template\Context $context
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param array $data
     */
    public function __construct(
        DirReader $dirReader,
        Template\Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dirReader = $dirReader;
        $this->_curl     = $curl;
    }

    /**
     * Render version fieldset
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return mixed
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '';
        if ($element->getData('group')['id'] == 'version') {
            $html = $this->toHtml();
        }
        return $html;
    }

    /**
     * Get installed module version from composer.json
     *
     * @return string
     */
    public function getVersion()
    {
        $installVersion = "unidentified";
        $composer = $this->getComposerInformation("NoFraud_Connect");

        if ($composer) {
            $installVersion = $composer['version'];
        }

        return $installVersion;
    }

    /**
     * Read composer.json for the given module
     *
     * @param string $moduleName
     * @return array|false
     */
    public function getComposerInformation($moduleName)
    {
        $dir = $this->dirReader->getModuleDir("", $moduleName);

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        if (file_exists($dir . '/composer.json')) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            return json_decode(file_get_contents($dir . '/composer.json'), true);
        }

        return false;
    }

    /**
     * Get template path
     *
     * @return string
     */
    public function getTemplate()
    {
        return 'NoFraud_Connect::system/config/fieldset/version.phtml';
    }
}

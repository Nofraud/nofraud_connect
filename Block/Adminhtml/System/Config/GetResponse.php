<?php
namespace NoFraud\Connect\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use NoFraud\Connect\Block\Adminhtml\System\Config\GetAuth;

/**
 * Class GetResponse
 *
 * @package NoFraud\Connect\Block\Adminhtml\System\Config
 */
class GetResponse extends Field
{
    /**
     * @var string
     */
    private $buttonLabel;

    /**
     * @return $this
     */
    public function setButtonLabel()
    {
        $this->buttonLabel = "Get Response";
        return $this;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('system/config/getresponse.phtml');
        }

        return $this;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $buttonLabel  = !empty($originalData['button_label']) ? $originalData['button_label'] : $this->buttonLabel;
        $this->addData(
            [
             'button_label' => __($buttonLabel),
             'html_id'      => $element->getHtmlId(),
             'ajax_url'     => $this->_urlBuilder->getUrl('nofraud/systemconfig/getResponse'),
            ]
        );

        return $this->_toHtml();
    }
}

<?php
 
namespace NoFraud\Connect\Model\Config\Source;

class CheckoutMode implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'prod', 'label' => __('Production')],
            ['value' => 'stag', 'label' => __('Sandbox')],
            ['value' => 'dev1', 'label' => __('Sandbox Test 1')],
            ['value' => 'dev2', 'label' => __('Sandbox Test 2')]
        ];
    }
}
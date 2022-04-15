<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Provides list of available servers
 */
class Server implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'https://dynamic-checkout-test.nofraud-test.com', 'label' => __('Dev')],
            ['value' => 'https://cdn-checkout-qe2.nofraud-test.com', 'label' => __('QA')],
            ['value' => 'https://cdn-checkout.nofraud.com', 'label' => __('Production')]
        ];
    }
}

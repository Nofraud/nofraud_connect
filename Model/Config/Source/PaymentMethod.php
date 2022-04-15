<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Helper\Data;

/**
 * Provides available Payment methods
 */
class PaymentMethod implements OptionSourceInterface
{
    /**
     * @var Data
     */
    protected $paymentHelper;

    /**
     * @param Data $paymentHelper
     */
    public function __construct(Data $paymentHelper)
    {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Returns array to be used in multiselect on back-end
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        // get available payment methods
        $arr = [];
        foreach ($this->paymentHelper->getPaymentMethodList() as $code => $title) {
            if (!empty($code) && !empty($title)) {
                $arr[] = ['value' => $code, 'label' => __($title) . " [$code]"];
            }
        }
        return $arr;
    }
}

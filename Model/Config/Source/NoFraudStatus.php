<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class NoFraudStatus implements OptionSourceInterface
{
    protected $paymentHelper;

    protected $statusCollectionFactory;
    
    public function __construct(\Magento\Payment\Helper\Data $paymentHelper,CollectionFactory $statusCollectionFactory)
    {
        $this->paymentHelper = $paymentHelper;
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Returns array to be used in multiselect on back-end
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->statusCollectionFactory->create()->toOptionArray();
    }
}

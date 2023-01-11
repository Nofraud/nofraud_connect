<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class NoFraudStatus implements OptionSourceInterface
{
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var StatusCollectionFactory
     */
    protected $statusCollectionFactory;

    /**
     * Constructor
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param CollectionFactory $statusCollectionFactory
     */
    public function __construct(\Magento\Payment\Helper\Data $paymentHelper, CollectionFactory $statusCollectionFactory)
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

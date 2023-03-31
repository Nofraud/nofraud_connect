<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class NoFraudStatusPass implements OptionSourceInterface
{

    /**
     * @var StatusCollectionFactory
     */
    protected $statusCollectionFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory $statusCollectionFactory
     */
    public function __construct(CollectionFactory $statusCollectionFactory)
    {
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Returns array to be used in select on back-end
     *
     * @return array
     */
    public function toOptionArray()
    {
        $statusOptions = $this->statusCollectionFactory->create()->toOptionArray();
        array_unshift($statusOptions, ['value' => null, 'label' => '-- Please Select --']);
        return $statusOptions;
    }
}

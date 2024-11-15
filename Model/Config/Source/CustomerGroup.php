<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

class CustomerGroup implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $_customerGroupCollectionFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory $groupCollectionFactory
     */
    public function __construct(CollectionFactory $groupCollectionFactory)
    {
        $this->_customerGroupCollectionFactory = $groupCollectionFactory;
    }

    /**
     * Returns array to be used in multi-select on back-end
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->_customerGroupCollectionFactory->create()->toOptionArray();
    }
}

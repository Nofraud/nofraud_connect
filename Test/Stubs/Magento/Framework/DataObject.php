<?php
namespace Magento\Framework;

class DataObject
{
    protected $_data = [];

    public function __construct(array $data = [])
    {
        $this->_data = $data;
    }

    public function getData($key = '')
    {
        if ($key === '') {
            return $this->_data;
        }
        return $this->_data[$key] ?? null;
    }

    public function setData($key, $value = null)
    {
        $this->_data[$key] = $value;
        return $this;
    }

    public function getId()
    {
        return $this->getData('id');
    }
}

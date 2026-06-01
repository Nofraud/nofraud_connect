<?php
namespace Magento\Customer\Api;

interface CustomerRepositoryInterface
{
    public function get($email, $websiteId = null);
    public function getById($customerId);
}

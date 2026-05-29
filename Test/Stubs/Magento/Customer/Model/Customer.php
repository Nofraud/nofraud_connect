<?php
namespace Magento\Customer\Model;

class Customer
{
    public function setWebsiteId($id) { return $this; }
    public function loadByEmail($email) { return $this; }
    public function getId() { return null; }
}

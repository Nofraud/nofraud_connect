<?php
namespace Magento\Framework\Exception;

class CommandException extends \Exception
{
    public function getRawMessage() { return $this->getMessage(); }
}

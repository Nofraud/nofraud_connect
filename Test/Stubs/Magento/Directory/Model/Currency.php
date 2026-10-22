<?php
namespace Magento\Directory\Model;

class Currency
{
    public function formatTxt($price, $options = []) { return (string) $price; }
}

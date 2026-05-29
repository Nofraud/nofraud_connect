<?php
namespace Magento\Directory\Model;

class Currency
{
    public function format($price, $options = [], $includeContainer = true) { return (string)$price; }
    public function formatTxt($price, $options = []) { return number_format((float)$price, 2, '.', ''); }
}

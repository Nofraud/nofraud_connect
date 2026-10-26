<?php
namespace Magento\Framework;

class Escaper
{
    public function escapeHtml($data, $allowedTags = null)
    {
        return htmlspecialchars((string)$data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    public function escapeHtmlAttr($string, $escapeSingleQuote = true)
    {
        return $this->escapeHtml($string);
    }

    public function escapeUrl($string)
    {
        return $this->escapeHtml($string);
    }
}

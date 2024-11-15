<?php
/**
 * Created by Nofraud Connect
 * Author: Soleil Cotterell
 * Date: 8/23/24
 */

namespace NoFraud\Connect\Helper;

class Notes extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param DirReader $dirReader
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
    ) {
        parent::__construct($context);
    }

    public function addNoteToOrder($order, $note): void
    {
        $order->addStatusHistoryComment("NoFraud: $note");
        $order->save();
    }
}

<?php

namespace NoFraud\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CronFrequency implements OptionSourceInterface
{
    /**
     * Returns array to be used in multiselect on back-end
     *
     * @return array
     */
    public function toOptionArray(){
        $cronTimeFrequency[] = ['value' => "*/2 * * * *", 'label' => __("Every 2 Minutes.")]; 
        $cronTimeFrequency[] = ['value' => "*/5 * * * *", 'label' => __("Every 5 Minutes.")]; 
        $cronTimeFrequency[] = ['value' => "*/15 * * * *", 'label' => __("Every 15 Minutes.")]; 
        $cronTimeFrequency[] = ['value' => "*/30 * * * *", 'label' => __("Every 30 Minutes.")]; 
        $cronTimeFrequency[] = ['value' => "*/45 * * * *", 'label' => __("Every 45 Minutes.")]; 
        $cronTimeFrequency[] = ['value' => "0 */1 * * *", 'label' => __("Every 1 Hours")]; 
        $cronTimeFrequency[] = ['value' => "0 */2 * * *", 'label' => __("Every 2 Hours")]; 
        $cronTimeFrequency[] = ['value' => "0 */4 * * *", 'label' => __("Every 4 Hours")]; 
        $cronTimeFrequency[] = ['value' => "0 */8 * * *", 'label' => __("Every 8 Hours")]; 
        $cronTimeFrequency[] = ['value' => "0 */16 * * *", 'label' => __("Every 16 Hours")]; 
        $cronTimeFrequency[] = ['value' => "0 */24 * * *", 'label' => __("Every 24 Hours")]; 
        return $cronTimeFrequency;
    }
}

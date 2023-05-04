<?php

namespace Amazon\Pay\Model\Config\Source;

class LoggingLevel implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'normal', 'label' => __('Normal')], ['value' => 'debug', 'label' => __('Debug')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return ['normal' => __('Normal'), 'debug' => __('Debug')];
    }

}

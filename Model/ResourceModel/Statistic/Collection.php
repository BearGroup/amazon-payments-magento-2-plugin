<?php

namespace Amazon\Pay\Model\ResourceModel\Statistic;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Collection constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Amazon\Pay\Model\Statistic::class, \Amazon\Pay\Model\ResourceModel\Statistic::class);
    }
}

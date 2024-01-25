<?php

namespace Amazon\Pay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Statistic extends AbstractDb
{

    public const TABLE_NAME = 'amazon_stat';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(static::TABLE_NAME, 'entity_id');
    }

}

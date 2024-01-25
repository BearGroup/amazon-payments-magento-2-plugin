<?php

namespace Amazon\Pay\Model;

use Amazon\Pay\Api\Data\StatisticInterface;
use Magento\Framework\Model\AbstractModel;
use Amazon\Pay\Model\ResourceModel\Statistic as StatisticResourceModel;
class Statistic extends AbstractModel implements StatisticInterface
{

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(StatisticResourceModel::class);
    }

    /**
     * @return array|mixed|null
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @return array|mixed|null
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @return array|mixed|null
     */
    public function getValue()
    {
        return $this->getData(self::VALUE);
    }

    /**
     * @param $orderId
     * @return Statistic
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @param $type
     * @return Statistic
     */
    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @param $value
     * @return Statistic
     */
    public function setValue($value)
    {
        return $this->setData(self::VALUE, $value);
    }
}

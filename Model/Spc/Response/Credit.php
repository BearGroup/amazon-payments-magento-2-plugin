<?php

namespace Amazon\Pay\Model\Spc\Response;

use Amazon\Pay\Api\Spc\Response\CreditInterface;
use Magento\Framework\DataObject;

class Credit extends DataObject implements CreditInterface
{
    /**
     * @inheritDoc
     */
    public function getType()
    {
        return $this->_getData('type');
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return $this->_getData('label');
    }

    /**
     * @inheritDoc
     */
    public function getCode()
    {
        return $this->_getData('code');
    }

    /**
     * @inheritDoc
     */
    public function getAmount()
    {
        return $this->_getData('amount');
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type)
    {
        return $this->setData('type', $type);
    }

    /**
     * @inheritDoc
     */
    public function setLabel(string $label)
    {
        return $this->setData('label', $label);
    }

    /**
     * @inheritDoc
     */
    public function setCode(string $code)
    {
        return $this->setData('code', $code);
    }

    /**
     * @inheritDoc
     */
    public function setAmount(string $amount)
    {
        // Formatting to two decimals
        $formattedAmount = number_format($amount, 2, '.', '');

        return $this->setData('amount', $formattedAmount);
    }
}

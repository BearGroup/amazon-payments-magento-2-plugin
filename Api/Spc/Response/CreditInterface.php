<?php

namespace Amazon\Pay\Api\Spc\Response;

interface CreditInterface
{
    const GIFT_CARD_CODE = 'gift_card';
    const GIFT_CARD_LABEL = 'Gift Card';
    const STORE_CREDIT_CODE = 'store_credit';
    const STORE_CREDIT_LABEL = 'Store Credit';
    const REWARDS_CODE = 'rewards';
    const REWARDS_LABEL = 'Rewards';

    /**
     * @return string
     */
    public function getType();

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getCode();

    /**
     * @return string
     */
    public function getAmount();

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type);

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label);

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code);

    /**
     * @param string $amount
     * @return $this
     */
    public function setAmount(string $amount);
}

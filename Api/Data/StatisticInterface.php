<?php

namespace Amazon\Pay\Api\Data;

interface StatisticInterface
{

    public const SESSION_INIT = 'session_init';
    public const SESSION_COMPLETE = 'session_complete';
    public const LWA_SIGN_IN = 'lwa_sign_in';

    public const LWA_SIGN_IN_CUSTOMER_CREATED = 'lwa_sign_in_customer_created';
    public const LWA_SIGN_IN_DISABLED = 'lwa_sign_in_disabled';
    public const LWA_SIGN_IN_SUCCESS = 'lwa_sign_in_success';
    public const LWA_SIGN_IN_ERROR = 'lwa_sign_in_error';
    public const EXPRESS_CHECKOUT_BUTTON_CART = 'express_checkout_button_cart';
    public const EXPRESS_CHECKOUT_BUTTON_MINICART = 'express_checkout_button_minicart';
    public const EXPRESS_CHECKOUT_BUTTON_PDP = 'express_checkout_button_pdp';
    public const EXPRESS_CHECKOUT_BUTTON_CHECKOUT = 'express_checkout_button_checkout';

    public const EXPRESS_CHECKOUT_ERROR = 'express_checkout_error';

    public const EXPRESS_CHECKOUT_SESSION_DATA = 'express_checkout_session_data';

    public const EXPRESS_CHECKOUT_PAYMENT_STATE = 'payment_state';

    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const TYPE = 'stat_type';
    public const VALUE = 'value';


    public function getId();

    public function getOrderId();

    public function getType();

    public function getValue();

    public function setOrderId($orderId);

    public function setType($type);

    public function setValue($value);
}

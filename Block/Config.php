<?php
/**
 * Copyright © Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 *  http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace Amazon\Pay\Block;

/**
 * Config
 *
 * @api
 *
 * Provides a block that displays links to available custom error logs in Amazon Pay admin/config section.
 */
class Config extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Amazon\Pay\Helper\Data
     */
    private $amazonHelper;

    /**
     * @var \Amazon\Pay\Model\AmazonConfig
     */
    private $amazonConfig;

    /**
     * @var \Amazon\Pay\Model\Subscription\SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * Config constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Amazon\Pay\Helper\Data $amazonHelper
     * @param \Amazon\Pay\Model\AmazonConfig $amazonConfig
     * @param \Amazon\Pay\Model\Subscription\SubscriptionManager $subscriptionManager
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Amazon\Pay\Helper\Data $amazonHelper,
        \Amazon\Pay\Model\AmazonConfig $amazonConfig,
        \Amazon\Pay\Model\Subscription\SubscriptionManager $subscriptionManager
    ) {
        parent::__construct($context);
        $this->amazonHelper = $amazonHelper;
        $this->amazonConfig = $amazonConfig;
        $this->subscriptionManager = $subscriptionManager;
    }

    /**
     * Package module configuration values for button rendering
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'region'                    => $this->amazonConfig->getRegion(),
            'code'                      => \Amazon\Pay\Gateway\Config\Config::CODE,
            'vault_code'                => \Amazon\Pay\Gateway\Config\Config::VAULT_CODE,
            'is_method_available'       => $this->amazonConfig->isPayButtonAvailableAsPaymentMethod(),
            'is_pay_only'               => $this->amazonHelper->isPayOnly(),
            'is_lwa_enabled'            => $this->isLwaEnabled(),
            'is_guest_checkout_enabled' => $this->amazonConfig->isGuestCheckoutEnabled(),
            'has_restricted_products'   => $this->amazonHelper->hasRestrictedProducts(),
            'is_multicurrency_enabled'  => $this->amazonConfig->multiCurrencyEnabled(),
            'acceptance_mark'           => $this->amazonConfig->getAcceptanceMark()
        ];

        if ($subscriptionLabel = $this->subscriptionManager->getSubscriptionLabel()) {
            $config['subscription_label'] = $subscriptionLabel;
        }

        return $config;
    }

    /**
     * Convert config values to JSON object
     *
     * @return string
     */
    public function getJsonConfig()
    {
        // JSON_HEX_TAG/JSON_HEX_AMP keep the payload safe to embed in an HTML <script> data block,
        // where entities are not decoded and only a literal "</script" could terminate it early.
        return json_encode($this->getConfig(), JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * Should a stale Amazon checkout session be discarded on this page?
     *
     * True everywhere except the checkout itself, so returning to the cart abandons a
     * half-finished Amazon checkout.
     *
     * @return bool
     */
    public function shouldClearCheckoutSession()
    {
        $request = $this->getRequest();

        return $request->getFrontName() != 'checkout'
            || strpos($request->getPathInfo(), 'checkout/cart') !== false;
    }

    /**
     * Return true if module is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->amazonConfig->isEnabled();
    }

    /**
     * Return true if Amazon Sign in is enabled
     *
     * @return bool
     */
    public function isLwaEnabled()
    {
        return $this->amazonConfig->isLwaEnabled();
    }
}

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
namespace Amazon\Pay\Controller;

use Amazon\Pay\Api\CheckoutSessionManagementInterface;
use Amazon\Pay\Api\CustomerLinkManagementInterface;
use Amazon\Pay\Api\Data\AmazonCustomerInterface;
use Amazon\Pay\Api\Data\StatisticInterface;
use Amazon\Pay\Domain\AmazonCustomerFactory;
use Amazon\Pay\Domain\ValidationCredentials;
use Amazon\Pay\Helper\Customer as CustomerHelper;
use Amazon\Pay\Helper\Session;
use Amazon\Pay\Helper\Statistic;
use Amazon\Pay\Model\Adapter\AmazonPayAdapter;
use Amazon\Pay\Model\AmazonConfig;
use Amazon\Pay\Model\Customer\MatcherInterface;
use Amazon\Pay\Model\Validator\AccessTokenRequestValidator;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;
use Psr\Log\LoggerInterface;

/**
 * Login with token controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class Login extends Action
{
    /**
     * @var AmazonCustomerFactory
     */
    protected $amazonCustomerFactory;

    /**
     * @var Adapter\AmazonPayAdapter
     */
    protected $amazonAdapter;

    /**
     * @var AmazonConfig
     */
    protected $amazonConfig;

    /**
     * @var Url
     */
    protected $customerUrl;

    /**
     * @var AccessTokenRequestValidator
     */
    protected $accessTokenRequestValidator;

    /**
     * @var AccountRedirect
     */
    protected $accountRedirect;

    /**
     * @var MatcherInterface
     */
    protected $matcher;

    /**
     * @var CustomerLinkManagementInterface
     */
    protected $customerLinkManagement;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var AccountManagementInterface
     */
    protected $accountManagement;

    /**
     * @var CheckoutSessionManagement
     */
    protected $checkoutSessionManagement;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var \Amazon\Pay\Controller\Statistic\Statistic
     */
    protected $statisticHelper;

    /**
     * Login constructor.
     *
     * @param Context $context
     * @param AmazonCustomerFactory $amazonCustomerFactory
     * @param \Amazon\Pay\Model\Adapter\AmazonPayAdapter $amazonAdapter
     * @param AmazonConfig $amazonConfig
     * @param Url $customerUrl
     * @param AccessTokenRequestValidator $accessTokenRequestValidator
     * @param AccountRedirect $accountRedirect
     * @param MatcherInterface $matcher
     * @param CustomerLinkManagementInterface $customerLinkManagement
     * @param CustomerSession $customerSession
     * @param Session $session
     * @param LoggerInterface $logger
     * @param StoreManager $storeManager
     * @param UrlInterface $url
     * @param AccountManagementInterface $accountManagement
     * @param CheckoutSessionManagementInterface $checkoutSessionManagement
     * @param customerHelper $customerHelper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        AmazonCustomerFactory $amazonCustomerFactory,
        AmazonPayAdapter $amazonAdapter,
        AmazonConfig $amazonConfig,
        Url $customerUrl,
        AccessTokenRequestValidator $accessTokenRequestValidator,
        AccountRedirect $accountRedirect,
        MatcherInterface $matcher,
        CustomerLinkManagementInterface $customerLinkManagement,
        CustomerSession $customerSession,
        Session $session,
        LoggerInterface $logger,
        StoreManager $storeManager,
        UrlInterface $url,
        AccountManagementInterface $accountManagement,
        CheckoutSessionManagementInterface $checkoutSessionManagement,
        CustomerHelper $customerHelper,
        Statistic $statisticHelper
    ) {
        $this->amazonCustomerFactory       = $amazonCustomerFactory;
        $this->amazonAdapter               = $amazonAdapter;
        $this->amazonConfig                = $amazonConfig;
        $this->customerUrl                 = $customerUrl;
        $this->accessTokenRequestValidator = $accessTokenRequestValidator;
        $this->accountRedirect             = $accountRedirect;
        $this->matcher                     = $matcher;
        $this->customerLinkManagement      = $customerLinkManagement;
        $this->customerSession             = $customerSession;
        $this->session                     = $session;
        $this->logger                      = $logger;
        $this->storeManager                = $storeManager;
        $this->url                         = $url;
        $this->accountManagement           = $accountManagement;
        $this->checkoutSessionManagement   = $checkoutSessionManagement;
        $this->customerHelper              = $customerHelper;
        $this->statisticHelper             = $statisticHelper;

        parent::__construct($context);
    }

    /**
     * Return true if Amazon returned buyerToken and no errors occurred
     *
     * @return bool
     */
    protected function isValidToken()
    {
        return $this->accessTokenRequestValidator->isValid($this->getRequest());
    }

    /**
     * Redirect buyer to Magento customer login URL
     *
     * @return ResponseInterface
     */
    protected function getRedirectLogin()
    {
        return $this->_redirect($this->customerUrl->getLoginUrl());
    }

    /**
     * Redirect buyer to Magento customer account page
     *
     * @return ResultRedirect|ResultForward
     */
    protected function getRedirectAccount()
    {
        return $this->accountRedirect->getRedirect();
    }

    /**
     * Get Amazon customer info from Magento table based on returned buyer info from Amazon
     *
     * @param mixed $buyerInfo
     * @return mixed
     */
    protected function getAmazonCustomer($buyerInfo)
    {
        return $this->customerHelper->getAmazonCustomer($buyerInfo);
    }

    /**
     * Handle Amazon Customer data after buyer logs in to Magento store
     *
     * Attempts to match Amazon customer data to Magento customer data. If the customer did not
     * previously exist, an account is created for them. If a match is found, but IDs are different,
     * the buyer is prompted for their Magento store password in order to link the Magento account
     * to the Amazon account.
     *
     * @param AmazonCustomerInterface $amazonCustomer
     * @return mixed
     */
    protected function processAmazonCustomer(AmazonCustomerInterface $amazonCustomer)
    {
        $customerData = $this->matcher->match($amazonCustomer);

        if (null === $customerData) {
            return $this->createCustomer($amazonCustomer);
        }

        if ($amazonCustomer->getId() != $customerData->getExtensionAttributes()->getAmazonId()) {
            if (! $this->session->isLoggedIn()) {
                return new ValidationCredentials($customerData->getId(), $amazonCustomer->getId());
            }

            $this->customerLinkManagement->updateLink($customerData->getId(), $amazonCustomer->getId());
        }

        $statisticData = [
            'stat_type' => StatisticInterface::LWA_SIGN_IN_SUCCESS,
            'am_customer_email' => $amazonCustomer->getEmail(),
            'am_customer_id' => $amazonCustomer->getId(),
            'value' => 'Login - New Customer Created'
        ];

        $this->statisticHelper->save($statisticData);

        return $customerData;
    }

    /**
     * Create a new Magento store account based on Amazon customer data
     *
     * @param AmazonCustomerInterface $amazonCustomer
     * @return mixed
     */
    protected function createCustomer(AmazonCustomerInterface $amazonCustomer)
    {
        $statisticData = [
            'stat_type' => StatisticInterface::LWA_SIGN_IN_CUSTOMER_CREATED,
            'am_customer_email' => $amazonCustomer->getEmail(),
            'am_customer_id' => $amazonCustomer->getId(),
            'value' => 'Login - New Customer Created'
        ];

        $this->statisticHelper->save($statisticData);

        return $this->customerHelper->createCustomer($amazonCustomer);
    }
}

<?php
/**
 * Copyright 2016 Amazon.com, Inc. or its affiliates. All Rights Reserved.
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
namespace Amazon\Payment\Controller\Payment;

use Amazon\Core\Model\AmazonConfig;
use Amazon\Core\Model\Config\Source\UpdateMechanism;
use Amazon\Payment\Api\Ipn\CompositeProcessorInterface;
use Amazon\Payment\Ipn\IpnHandlerFactoryInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use \Magento\Checkout\Model\Session as CheckoutSession;

class CompleteCheckout extends Action
{

    /**
     * @var AmazonConfig
     */
    private $amazonConfig;

    private $checkoutSession;

    private $cartManagement;

    private $pageFactory;

    public function __construct(
        Context $context,
        AmazonConfig $amazonConfig,
        CartManagementInterface $cartManagement,
        GuestCartManagementInterface $guestCartManagement,
        CheckoutSession $checkoutSession,
        \Magento\Customer\Model\Session $session,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    ) {
        parent::__construct($context);
        $this->amazonConfig = $amazonConfig;
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession;
        $this->session = $session;
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        //STUB: complete order processing
        $authenticationStatus = $this->getRequest()->getParam('AuthenticationStatus');
        switch($authenticationStatus) {
            case 'Success':
                try {
                    if (!$this->session->isLoggedIn()) {
                        $this->checkoutSession->getQuote()->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
                    }
                    $this->cartManagement->placeOrder($this->checkoutSession->getQuoteId());
                    return $this->_redirect('checkout/onepage/success');
                } catch(\Exception $e) {  //TODO: handle only certain exception classes here
                    $this->checkoutSession->getQuote()->setError($e->getMessage());
                }
                break;
            case 'Failure':
                $this->checkoutSession->getQuote()->setError(
                    'Amazon Pay was unable to authenticate the payment instrument.  '
                    . 'Please try again, or use a different payment method.'
                );
                break;
            case 'Abandoned':
            default:
                $this->checkoutSession->getQuote()->setError(
                    'The SCA challenge was not completed successfully.  '
                    . 'Please try again, or use a different payment method.'
                );
        }
        return $this->pageFactory->create();
    }
}

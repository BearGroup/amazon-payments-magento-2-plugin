<?php

namespace Amazon\Pay\Test\Mftf\Helper;

use Facebook\WebDriver\Exception\ElementNotInteractableException;
use Magento\FunctionalTestingFramework\Helper\Helper;

class UseAddressOrPaymentMethod extends Helper
{
    public function clickUseAddressOrPaymentMethod(
        $addressOrPaymentMethodRadioButtonSelector,
        $useAddressOrPaymentMethodSelector
    ) {
        /** @var \Magento\FunctionalTestingFramework\Module\MagentoWebDriver $webDriver */
        $webDriver = $this->getModule('\Magento\FunctionalTestingFramework\Module\MagentoWebDriver');
        $waitTime = 15000;

        try {
            $webDriver->waitForElementClickable($addressOrPaymentMethodRadioButtonSelector, $waitTime);
            $webDriver->click($addressOrPaymentMethodRadioButtonSelector);
            $webDriver->waitForElementClickable($useAddressOrPaymentMethodSelector, $waitTime);
            $webDriver->click($useAddressOrPaymentMethodSelector);
        } catch (\Exception $e) {
            // Avoid out of memory error sometimes caused by print_r
            // print_r($e);
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * Attempt click, but fallback on js click as the first seems to fail often
     * @param $checkoutButtonSelector
     * @return void
     * @throws \Codeception\Exception\ModuleException
     */
    public function clickAmazonCheckoutButton(
        $checkoutButtonSelector
    ) {

        /** @var \Magento\FunctionalTestingFramework\Module\MagentoWebDriver $webDriver */
        $webDriver = $this->getModule('\Magento\FunctionalTestingFramework\Module\MagentoWebDriver');

        try {
            $webDriver->click($checkoutButtonSelector);
        } catch (ElementNotInteractableException $e) {
            // Handle the exception
//            $webDriver->debug("Element not interactable: " . $e->getMessage());
            echo "Element not interactable: " . $e->getMessage() . PHP_EOL;

            // Attempt to Manually find and click the button using JavaScript
//            $webDriver->debug("Retrying with js...");
            echo "Retrying with js..." . PHP_EOL;
            $webDriver->executeJS("document.querySelector('$checkoutButtonSelector').click();");
        }
    }
}

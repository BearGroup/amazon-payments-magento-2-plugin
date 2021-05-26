<?php
/**
 * Copyright © Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 */

namespace Amazon\Pay\Model\Config;

use Amazon\Pay\Helper\Data as AmazonHelper;
use Amazon\Pay\Model\AmazonConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\Type\Config as CacheTypeConfig;
use Magento\Backend\Model\UrlInterface;
use Magento\Payment\Helper\Formatter;
use \phpseclib\Crypt\RSA;
use \phpseclib\Crypt\AES;

/**
 * // @TODO: remove this?
 * @  SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 */
class AutoKeyExchange
{

    const CONFIG_XML_PATH_PRIVATE_KEY = 'payment/amazon_payment/autokeyexchange/privatekey';
    const CONFIG_XML_PATH_PUBLIC_KEY  = 'payment/amazon_payment/autokeyexchange/publickey';
    const CONFIG_XML_PATH_AUTH_TOKEN  = 'payment/amazon_payment/autokeyexchange/auth_token';

    private $_spIds = [
        'USD' => 'AUGT0HMCLQVX1',
        'GBP' => 'A1BJXVS5F6XP',
        'EUR' => 'A2ZAYEJU54T1BM',
        'JPY' => 'A1MCJZEB1HY93J',
    ];

    private $_mapCurrencyRegion = [
        'EUR' => 'de',
        'USD' => 'us',
        'GBP' => 'uk',
        'JPY' => 'ja',
    ];

    /**
     * @var
     */
    private $_storeId;

    /**
     * @var
     */
    private $_websiteId;

    /**
     * @var string
     */
    private $_scope;

    /**
     * @var int
     */
    private $_scopeId;

    /**
     * @var AmazonHelper
     */
    private $amazonHelper;

    /**
     * @var AmazonConfig
     */
    private $amazonConfig;
    private \Magento\Framework\App\Config\ConfigResource\ConfigInterface $config;
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;
    private \Magento\Framework\App\ProductMetadataInterface $productMeta;
    private \Magento\Framework\Encryption\EncryptorInterface $encryptor;
    private UrlInterface $backendUrl;
    private \Magento\Framework\App\Cache\Manager $cacheManager;
    private \Magento\Framework\App\ResourceConnection $connection;
    private State $state;
    private \Magento\Framework\App\Request\Http $request;
    private \Magento\Store\Model\StoreManagerInterface $storeManager;
    private \Psr\Log\LoggerInterface $logger;
    private \Magento\Framework\Message\ManagerInterface $messageManager;
    private \Magento\Framework\Math\Random $mathRandom;

    /**
     * @param AmazonHelper $coreHelper
     * @param AmazonConfig $amazonConfig
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $config
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\ProductMetadataInterface $productMeta
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\ResourceConnection $connection
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     * @param \Magento\Framework\App\Request\Http $request
     * @param State $state
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param UrlInterface $backendUrl
     * @param \Magento\Framework\Math\Random $mathRandom
     * @param \Psr\Log\LoggerInterface $logger
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        AmazonHelper $amazonHelper,
        AmazonConfig $amazonConfig,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $config,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\ProductMetadataInterface $productMeta,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\ResourceConnection $connection,
        \Magento\Framework\App\Cache\Manager $cacheManager,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\App\State $state,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        \Magento\Framework\Math\Random $mathRandom,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->amazonHelper  = $amazonHelper;
        $this->amazonConfig  = $amazonConfig;
        $this->config        = $config;
        $this->scopeConfig   = $scopeConfig;
        $this->productMeta   = $productMeta;
        $this->encryptor     = $encryptor;
        $this->backendUrl    = $backendUrl;
        $this->cacheManager  = $cacheManager;
        $this->connection    = $connection;
        $this->state         = $state;
        $this->request       = $request;
        $this->storeManager  = $storeManager;
        $this->mathRandom    = $mathRandom;
        $this->logger        = $logger;

        $this->messageManager = $messageManager;

        // Find store ID and scope
        $this->_websiteId = $request->getParam('website', 0);
        $this->_storeId   = $request->getParam('store', 0);
        $this->_scope     = $request->getParam('scope');

        // Website scope
        if ($this->_websiteId) {
            $this->_scope = !$this->_scope ? 'websites' : $this->_scope;
        } else {
            $this->_websiteId = $storeManager->getWebsite()->getId();
        }

        // Store scope
        if ($this->_storeId) {
            $this->_websiteId = $this->storeManager->getStore($this->_storeId)->getWebsite()->getId();
            $this->_scope = !$this->_scope ? 'stores' : $this->_scope;
        } else {
            $this->_storeId = $storeManager->getWebsite($this->_websiteId)->getDefaultStore()->getId();
        }

        // Set scope ID
        switch ($this->_scope) {
            case 'websites':
                $this->_scopeId = $this->_websiteId;
                break;
            case 'stores':
                $this->_scopeId = $this->_storeId;
                break;
            default:
                $this->_scope = 'default';
                $this->_scopeId = 0;
                break;
        }
    }

    /**
     * Return domain
     */
    private function getEndpointDomain()
    {
        return in_array($this->getConfig('currency/options/default'), ['EUR', 'GBP'])
            ? 'https://payments-eu.amazon.com/'
            : 'https://payments.amazon.com/';
    }

    /**
     * Return register popup endpoint URL
     */
    public function getEndpointRegister()
    {
        return $this->getEndpointDomain() . 'register';
    }

    /**
     * Return pubkey endpoint URL
     */
    public function getEndpointPubkey()
    {
        return $this->getEndpointDomain() . 'register/getpublickey';
    }

    /**
     * Return listener origins
     */
    public function getListenerOrigins()
    {
        return [
            'payments.amazon.com',
            'payments-eu.amazon.com',
            'sellercentral.amazon.com',
            'sellercentral-europe.amazon.com'
        ];
    }

    /**
     * Generate and save RSA keys
     */
    public function generateKeys()
    {
        $rsa = new RSA();
        $keys = $rsa->createKey(2048);
        $encrypt = $this->encryptor->encrypt($keys['privatekey']);

        $this->config
            ->saveConfig(self::CONFIG_XML_PATH_PUBLIC_KEY, $keys['publickey'], 'default', 0)
            ->saveConfig(self::CONFIG_XML_PATH_PRIVATE_KEY, $encrypt, 'default', 0);

        $this->cacheManager->clean([CacheTypeConfig::TYPE_IDENTIFIER]);

        return $keys;
    }

    /**
     * Generate and save auth token
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateAuthToken()
    {
        $authToken = $this->mathRandom->getUniqueHash();
        $this->config->saveConfig(self::CONFIG_XML_PATH_AUTH_TOKEN, $authToken, 'default', 0);
        return $authToken;
    }

    /**
     * Delete key-pair from config
     */
    public function destroyKeys()
    {
        $this->config
            ->deleteConfig(self::CONFIG_XML_PATH_PUBLIC_KEY, 'default', 0)
            ->deleteConfig(self::CONFIG_XML_PATH_PRIVATE_KEY, 'default', 0)
            ->deleteConfig(self::CONFIG_XML_PATH_AUTH_TOKEN, 'default', 0);

        $this->cacheManager->clean([CacheTypeConfig::TYPE_IDENTIFIER]);
    }

    /**
     * Return RSA public key
     *
     * @param bool $pemformat
     * @param bool $reset
     * @return mixed|string|string[]|null
     */
    public function getPublicKey($pemformat = false, $reset = false)
    {
        $publickey = $this->scopeConfig->getValue(self::CONFIG_XML_PATH_PUBLIC_KEY, 'default', 0);

        // Generate key pair
        if (!$publickey || $reset || strlen($publickey) < 300) {
            $keys = $this->generateKeys();
            $publickey = $keys['publickey'];
        }

        if (!$pemformat) {
            $pubtrim   = ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"];
            $publickey = str_replace($pubtrim, ['','',''], $publickey);
            // Remove binary characters
            $publickey = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $publickey);
        }
        return $publickey;
    }

    /**
     * Return RSA private key
     */
    public function getPrivateKey()
    {
        return $this->encryptor->decrypt($this->scopeConfig->getValue(self::CONFIG_XML_PATH_PRIVATE_KEY, 'default', 0));
    }

    /**
     * Verify and decrypt JSON payload
     *
     * @param                                        string $payloadJson
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function decryptPayload($payloadJson, $autoEnable = true, $autoSave = true)
    {
        try {
            $payload = (object) json_decode($payloadJson);

            $publicKeyId = urldecode($payload->publicKeyId);
            $decryptedKey = null;

            $success = openssl_private_decrypt(
                base64_decode($publicKeyId), // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $decryptedKey,
                $this->getPrivateKey()
            );

            if ($success) {
                $config = [
                    'merchant_id' => $payload->merchantId,
                    'store_id' => $payload->storeId,
                    'private_key' => $this->getPrivateKey(),
                    'public_key_id' => $decryptedKey,
                ];
                $this->saveToConfig($config);
                $this->destroyKeys();
            }
            return $success;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addError(__($e->getMessage()));
            $link = 'https://payments.amazon.com/help/202024240'; // @TODO: update for magento 2?
            $this->messageManager->addError(
                __(
                    "If you're experiencing consistent errors with transferring keys, " .
                    "click <a href=\"%1\" target=\"_blank\">Manual Transfer Instructions</a> to learn more.",
                    $link
                )
            );
        }

        return false;
    }

    /**
     * Save values to Magento config
     *
     * @param $config
     * @param bool $autoEnable
     * @return bool
     */
    public function saveToConfig($config, $autoEnable = true)
    {
        $this->config->saveConfig(
            'payment/amazon_payment_v2/merchant_id',
            $config['merchant_id'],
            $this->_scope,
            $this->_scopeId
        );
        $this->config->saveConfig(
            'payment/amazon_payment_v2/store_id',
            $config['store_id'],
            $this->_scope,
            $this->_scopeId
        );
        $this->config->saveConfig(
            'payment/amazon_payment_v2/private_key',
            $this->encryptor->encrypt($config['private_key']),
            $this->_scope,
            $this->_scopeId
        );
        $this->config->saveConfig(
            'payment/amazon_payment_v2/public_key_id',
            $config['public_key_id'],
            $this->_scope,
            $this->_scopeId
        );

        $currency = $this->getConfig('currency/options/default');
        if (isset($this->_mapCurrencyRegion[$currency])) {
            $this->config->saveConfig(
                'payment/amazon_payment/payment_region',
                $this->_mapCurrencyRegion[$currency],
                $this->_scope,
                $this->_scopeId
            );
        }
        $this->config->saveConfig(
            'payment/amazon_payment/sandbox',
            '0'
        );

        if ($autoEnable) {
            $this->autoEnable();
        }

        $this->cacheManager->clean([CacheTypeConfig::TYPE_IDENTIFIER]);

        return true;
    }

    /**
     * Auto-enable payment method
     */
    public function autoEnable()
    {
        if (!$this->getConfig('payment/amazon_payment_v2/active')) {
            $this->config->saveConfig('payment/amazon_payment_v2/active', true, $this->_scope, $this->_scopeId);
            $this->messageManager->addSuccessMessage(__("Amazon Pay is now enabled."));
        }
    }

    /**
     * Return listener URL
     */
    public function getReturnUrl()
    {
        $baseUrl = $this->storeManager->getStore($this->_storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        $baseUrl = str_replace('http:', 'https:', $baseUrl);
        $authToken = $this->getConfig(self::CONFIG_XML_PATH_AUTH_TOKEN, 'default', 0);
        if (empty($authToken)) {
            $authToken = $this->generateAuthToken();
        }
        $params  = 'website=' . $this->_websiteId .
            '&store=' . $this->_storeId .
            '&scope=' . $this->_scope .
            '&auth=' . $authToken;
        return $baseUrl . 'amazon_pay/autokeyexchange/listener?' . $params;
    }

    /**
     * Return array of form POST params for Auto Key Exchange sign up
     */
    public function getFormParams()
    {
        // Get redirect URLs and store URL-s
        $urlArray = [];
        $baseUrls = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            // Get secure base URL
            if ($baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true)) {
                $value = $baseUrl . 'amazon/login/processAuthHash/';  // @TODO: wat?
                $urlArray[] = $value;
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $url = parse_url($baseUrl);
                if (isset($url['host'])) {
                    $baseUrls[] = 'https://' . $url['host'];
                }
            }
            // Get unsecure base URL
            if ($baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, false)) {
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $url = parse_url($baseUrl);
                if (isset($url['host'])) {
                    $baseUrls[] = 'https://' . $url['host'];
                }
            }
        }
        $urlArray = array_unique($urlArray);
        $baseUrls = array_unique($baseUrls);

        $moduleVersion = $this->amazonHelper->getModuleVersion();
        if ($moduleVersion == "Read error!") {
            $moduleVersion = '--';
        }

        $currency = $this->getConfig('currency/options/default');

        return [
            'keyShareURL' => $this->getReturnUrl(),
            'publicKey'   => $this->getPublicKey(),
            'locale'      => $this->getConfig('general/locale/code'),
            'source'      => 'SPPL',
            'spId'        => isset($this->_spIds[$currency]) ? $this->_spIds[$currency] : '',
            'onboardingVersion' => '2',
            'spSoftwareVersion'           => $this->productMeta->getVersion(),
            'spAmazonPluginVersion'       => $moduleVersion,
            'merchantStoreDescription'    => $this->getConfig('general/store_information/name'),
            'merchantLoginDomains[]'      => $baseUrls,
            'merchantLoginRedirectURLs[]' => $urlArray,
        ];
    }

    /**
     * Return config value based on scope and scope ID
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, $this->_scope, $this->_scopeId);
    }

    /**
     * Return payment region based on currency
     */
    public function getRegion()
    {
        $currency = $this->getCurrency();

        $region = null;
        if ($currency) { // @TODO: refactor to be more clear
            $region = isset($this->_mapCurrencyRegion[$currency]) ?
                strtoupper($this->_mapCurrencyRegion[$currency]) :
                'DE';
        }

        if ($region == 'DE') {
            $region = 'Euro Region';
        }

        return $region ? $region : 'US';
    }

    /**
     * Return a valid store currency, otherwise return null
     */
    public function getCurrency()
    {
        $currency = $this->getConfig('currency/options/default');
        $isCurrencyValid = isset($this->_mapCurrencyRegion[$currency]);
        if (!$isCurrencyValid) {
            if ($this->amazonConfig->isActive($this->_scope, $this->_scopeId)) { // @TODO: check what this is doing
                $isCurrencyValid = $this->amazonConfig->canUseCurrency($currency, $this->_scope, $this->_scopeId);
            } else {
                $isCurrencyValid = in_array(
                    $currency,
                    $this->amazonConfig->getValidCurrencies($this->_scope, $this->_scopeId)
                );
            }
        }
        return $isCurrencyValid ? $currency : null;
    }

    /**
     * Return merchant country
     */
    public function getCountry()
    {
        $co = $this->getConfig('paypal/general/merchant_country');
        return $co ?: 'US';
    }

    /**
     * Validate provided auth token against the one stored in the database
     *
     * @param $authToken
     * @return bool
     */
    public function validateAuthToken($authToken)
    {
        return $this->getConfig(self::CONFIG_XML_PATH_AUTH_TOKEN, 'default', 0) == $authToken;
    }

    /**
     * Return array of config for JSON Amazon Auto Key Exchange variables.
     */
    public function getJsonAmazonAKEConfig()
    {
        // @TODO: review which of these are required
        return [
            'co'            => $this->getCountry(),
            'region'        => $this->getRegion(),
            'currency'      => $this->getCurrency(),
            'amazonUrl'     => $this->getEndpointRegister(),
            'pollUrl'       => $this->backendUrl->getUrl('amazon/autokeyexchange/poll/'),
            'isSecure'      => (int) ($this->request->isSecure()),
            'hasOpenssl'    => (int) (extension_loaded('openssl')),
            'formParams'    => $this->getFormParams(),
            'isMultiCurrencyRegion' => (int) $this->amazonConfig->isMulticurrencyRegion($this->_scope, $this->_scopeId),
        ];
    }
}

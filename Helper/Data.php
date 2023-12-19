<?php

namespace NoFraud\Checkout\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;

class Data extends AbstractHelper
{
    protected $_config;
    protected $_storeManager;
    protected $resourceConnection;

    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'error',
    ];

    /** 
     * Checkout BASE URL Key
     **/
    const CHECKOUT_API_BASE_URL_KEY = "checkout_api_base_url";

    /*
    * Checkout JS URLS Key
    **/
    const API_SOURCE_JS_KEY = "js_base_url";

    /** 
     * Checkout Cancel Transaction Key
     **/
    const PORTAL_CANCEL_BASE_URL_KEY = "cancel_transaction_base_url";

    /**
     * Checkout Status By URL Key
     **/
    const NFAPI_STATUS_BASE_URL_KEY = "nf_api_status_base_url";

    /* 
    * Payment Button APP BASE URLS Key
    **/
    const PAYMENT_APP_BASE_URL_KEY = "payment_app_base_url";

    const ORDER_STATUSES = 'nofraud/order_statuses';

    public function __construct(
        Context               $context,
        StoreManagerInterface $storeManager,
        ResourceConnection    $resourceConnection
    ) {
        $this->_storeManager      = $storeManager;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context);
    }

    /**
     * get nofraud checkout enabled
     */
    public function getEnabled()
    {
        return $this->scopeConfig->getValue(
            'nofraud/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * get Merchant Id
     */
    public function getMerchantId()
    {
        return $this->scopeConfig->getValue(
            'nofraud/general/merchant',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * get Access token not login
     */
    public function getAccessTokenNotLogin()
    {
        return $this->scopeConfig->getValue(
            'nofraud/general/access_token_not_login',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * get No Fraud base url
     */
    public function getNfBaseUrl($dataKey)
    {
        $connection      = $this->resourceConnection->getConnection();
        $NFCheckoutTable = $this->resourceConnection->getTableName('nofraud_checkout');

        $fetchQuery = "SELECT data_value FROM `" . $NFCheckoutTable . "` WHERE `data_key` = ?";
        $dataValue  = $connection->fetchOne($fetchQuery, [$dataKey]);

        return $dataValue !== false ? $dataValue : '';
    }

    /**
     * get API Source JS URL
     */
    public function getApiSourceJs()
    {
        $url = $this->getNfBaseUrl(self::API_SOURCE_JS_KEY);

        return $url;
    }

    /**
     * Get Capture APi URL
     */
    public function getCaptureTransactionApiUrl()
    {
        $merchantId = $this->getMerchantId();
        $url        = $this->getNfBaseUrl(self::CHECKOUT_API_BASE_URL_KEY) . "/api/v2/hooks/capture/$merchantId";

        return $url;
    }

    /**
     * get Refund APi URL
     */
    public function getRefundApiUrl()
    {
        $merchantId = $this->getMerchantId();
        $url        = $this->getNfBaseUrl(self::CHECKOUT_API_BASE_URL_KEY) . "/api/v2/hooks/refund/$merchantId";

        return $url;
    }

    /**
     * get Cancel APi URL
     */
    public function getCancelTransactionApiUrl()
    {
        $url = $this->getNfBaseUrl(self::PORTAL_CANCEL_BASE_URL_KEY) . "/api/v1/transaction-update/cancel-transaction";

        return $url;
    }

    /**
     * get Status By Url APi URL
     */
    public function getStatusByUrlApiUrl()
    {
        $url = $this->getNfBaseUrl(self::NFAPI_STATUS_BASE_URL_KEY) . "/status_by_url/";

        return $url;
    }

    /**
     * get Merchant Preferences
     */
    public function getNofraudMerSettings()
    {
        $url = $this->getNfBaseUrl(self::CHECKOUT_API_BASE_URL_KEY) . "/api/v2/merchants/";

        return $url;
    }

    /**
     * get Refund APi Key
     */
    public function getRefundApiKey()
    {
        return $this->getNofrudCheckoutAppNfToken();
    }

    /**
     * get Cancel Transaction nf_token
     */
    public function getNofrudCheckoutAppNfToken()
    {
        return $this->scopeConfig->getValue(
            'nofraud/general/nf_token',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * get Cancel Transaction nf_token
     */
    public function getCanelTransactionNfToken()
    {
        return $this->scopeConfig->getValue(
            'nofraud/general/nf_token',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * get Capture APi Key
     */
    public function getCaptureApiKey()
    {
        return $this->getNofrudCheckoutAppNfToken();
    }

    /**
     * get getCustomStatusConfig
     */
    public function getCustomStatusConfig($statusName, $storeId = null)
    {
        if (!in_array($statusName, $this->orderStatusesKeys)) {
            return;
        }
        $path = self::ORDER_STATUSES . '/' . $statusName;
        return $this->_getConfigValueByStoreId($path, $storeId);
    }

    /**
     * get config value by store id
     */
    private function _getConfigValueByStoreId($path, $storeId)
    {
        if (is_null($storeId)) {
            return $this->scopeConfig->getValue($path);
        }
        $value = $this->scopeConfig->getValue($path, 'store', $storeId);
        if (empty($value)) {
            $value = $this->scopeConfig->getValue($path);
        }
        return $value;
    }

    /**
     * get Payment Button API Source JS URL
     */
    public function getPaymentButtonScriptApiSourceJs()
    {
        $merchantId = $this->getMerchantId();
        $url        = $this->getNfBaseUrl(self::CHECKOUT_API_BASE_URL_KEY) . "/api/v1/merchants/$merchantId/script.js";

        return $url;
    }

    /**
     * get Payment Button APP Source JS URL
     */
    public function getPaymentButtonMagentoAppSourceJs()
    {
        $url = $this->getNfBaseUrl(self::PAYMENT_APP_BASE_URL_KEY) . "/payment-options/scripts/magento.js";

        return $url;
    }
}

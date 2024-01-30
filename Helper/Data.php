<?php
namespace NoFraud\Checkout\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper {

    protected $_config;

    protected $_storeManager;

    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'error',
    ];

    /** 
    * Checkout BASE URLS
    **/
    const PROD_CHECKOUT_API_BASE_URL = "https://dynamic-api-checkout.nofraud.com";
    const STAG_CHECKOUT_API_BASE_URL = "https://dynamic-api-checkout-qe2.nofraud-test.com";
    const DEV_CHECKOUT_API_BASE_URL  = "https://dynamic-checkout-api-staging2.nofraud-test.com";
    
    /* 
    * Checkout JS URLS 
    **/
    const PROD_API_SOURCE_JS = "https://cdn-checkout.nofraud.com/scripts/nf-src-magento.js";
    const STAG_API_SOURCE_JS = "https://cdn-checkout-qe2.nofraud-test.com/scripts/nf-src-magento.js";
    const DEV_API_SOURCE_JS  = "https://dynamic-checkout-test.nofraud-test.com/latest/scripts/nf-src-magento.js";

    /** 
    * Checkout Api Refund Endpoints
    **/
    const PROD_REFUND_API_URL = self::PROD_CHECKOUT_API_BASE_URL."/api/v2/hooks/refund/";
    const STAG_REFUND_API_URL = self::STAG_CHECKOUT_API_BASE_URL."/api/v2/hooks/refund/";
    const DEV_REFUND_API_URL  = self::DEV_CHECKOUT_API_BASE_URL."/api/v2/hooks/refund/";

    /** 
    * Checkout Api Capture Endpoints
    **/
    const PROD_CAPTURE_API_URL = self::PROD_CHECKOUT_API_BASE_URL."/api/v2/hooks/capture/";
    const STAG_CAPTURE_API_URL = self::STAG_CHECKOUT_API_BASE_URL."/api/v2/hooks/capture/";
    const DEV_CAPTURE_API_URL  = self::DEV_CHECKOUT_API_BASE_URL."/api/v2/hooks/capture/";

    /** 
    * Checkout Merchat Settings Endpoints
    **/
    const PROD_NFAPI_MER_BASE_URL = self::PROD_CHECKOUT_API_BASE_URL."/api/v2/merchants/";
    const STAG_NFAPI_MER_BASE_URL = self::STAG_CHECKOUT_API_BASE_URL."/api/v2/merchants/";
    const DEV_NFAPI_MER_BASE_URL  = self::DEV_CHECKOUT_API_BASE_URL."/api/v2/merchants/";

    /** 
    * Checkout Cancel Transaction Endpoints
    **/
    const PROD_PORTAL_CANCEL_BASE_URL = "https://portal.nofraud.com/api/v1/transaction-update/cancel-transaction";
    const STAG_PORTAL_CANCEL_BASE_URL = "https://portal-ami-qe2.nofraud-test.com/api/v1/transaction-update/cancel-transaction";
    const DEV_PORTAL_CANCEL_BASE_URL  = "https://portal-ami-qe2.nofraud-test.com/api/v1/transaction-update/cancel-transaction";

    /**
     * Checkout Status By URL Endpoints
    **/
    const PROD_NFAPI_STATUS_BASE_URL = "https://api.nofraud.com/status_by_url/";
    const STAG_NFAPI_STATUS_BASE_URL = "https://api-qe2.nofraud-test.com/status_by_url/";
    const DEV_NFAPI_STATUS_BASE_URL  = "https://api-qe2.nofraud-test.com/status_by_url/";

    /* 
    * Payment Button APP BASE URLS 
    **/
    const PROD_PAYMENT_APP_BASE_URL = "https://cdn-checkout.nofraud.com/";
    const STAG_PAYMENT_APP_BASE_URL = "https://cdn-checkout-qe2.nofraud-test.com/";
    const DEV_PAYMENT_APP_BASE_URL  = "https://dynamic-checkout-test.nofraud-test.com/latest/";

    const ORDER_STATUSES = 'nofraud/order_statuses';

    public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * get Nofruad checkout mode
     */
    public function getNofraudAdvanceListMode()
    {
        return $this->scopeConfig->getValue(
            'nofraud/advance/list_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
    * get Merchant Id
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
    * get API Source JS URL
    */
	public function getApiSourceJs()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        if( strcmp($checkoutMode,"prod") === 0 ){
            return self::PROD_API_SOURCE_JS;
        }elseif( strcmp($checkoutMode,"stag") === 0 ){
            return self::STAG_API_SOURCE_JS;
        }elseif( strcmp($checkoutMode,"dev") === 0 ) {
            return self::DEV_API_SOURCE_JS;
        }
    }

    /**
     * Get Capture APi URL
     */
    public function getCaptureTransactionApiUrl()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        $merchantId   = $this->getMerchantId();
        if( strcmp($checkoutMode,"prod") === 0 ){
            return self::PROD_CAPTURE_API_URL.$merchantId;
        }elseif( strcmp($checkoutMode,"stag") === 0 ){
            return self::STAG_CAPTURE_API_URL.$merchantId;
        }elseif( strcmp($checkoutMode,"dev") === 0 ) {
            return self::DEV_CAPTURE_API_URL.$merchantId;
        }
    }
	
    /**
    * get Refund APi URL
    */
    public function getRefundApiUrl()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        $merchantId   = $this->getMerchantId();
        if( strcmp($checkoutMode,"prod") === 0 ){
            return self::PROD_REFUND_API_URL.$merchantId;
        }elseif( strcmp($checkoutMode,"stag") === 0 ){
            return self::STAG_REFUND_API_URL.$merchantId;
        }elseif( strcmp($checkoutMode,"dev") === 0 ) {
            return self::DEV_REFUND_API_URL.$merchantId;
        }
    }

    /**
     * get Cancel APi URL
     */
    public function getCancelTransactionApiUrl()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        if( strcmp($checkoutMode,"prod") === 0 ){
            return self::PROD_PORTAL_CANCEL_BASE_URL;
        }elseif( strcmp($checkoutMode,"stag") === 0 ){
            return self::STAG_PORTAL_CANCEL_BASE_URL;
        }elseif( strcmp($checkoutMode,"dev") === 0 ) {
            return self::DEV_PORTAL_CANCEL_BASE_URL;
        }
    }

    /**
     * get Status By Url APi URL
     */
    public function getStatusByUrlApiUrl()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        if( strcmp($checkoutMode,"prod") === 0 ){
            return self::PROD_NFAPI_STATUS_BASE_URL;
        }elseif( strcmp($checkoutMode,"stag") === 0 ){
            return self::STAG_NFAPI_STATUS_BASE_URL;
        }elseif( strcmp($checkoutMode,"dev") === 0 ) {
            return self::DEV_NFAPI_STATUS_BASE_URL;
        }
    }

    /**
     * get Merchant Preferences
     */
    public function getNofraudMerSettings()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        if( strcmp($checkoutMode,"prod") === 0 ){
            return self::PROD_NFAPI_MER_BASE_URL;
        }elseif( strcmp($checkoutMode,"stag") === 0 ){
            return self::STAG_NFAPI_MER_BASE_URL;
        }elseif( strcmp($checkoutMode,"dev") === 0 ) {
            return self::DEV_NFAPI_MER_BASE_URL;
        }
    }
    
    /**
    * get Refund APi Key
    */
    public function getRefundApiKey()
    {
        /*return $this->scopeConfig->getValue(
            'nofraud/general/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );*/
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
        /*return $this->scopeConfig->getValue(
            'nofraud/general/api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );*/
	return $this->getNofrudCheckoutAppNfToken();
    }

    /**
     * get getCustomStatusConfig
     */
    public function getCustomStatusConfig($statusName, $storeId = null)
    {
        if (!in_array($statusName,$this->orderStatusesKeys)) {
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
        if(empty($value)){
            $value = $this->scopeConfig->getValue($path);
        }
        return $value;
    }

    /**
    * get Payment Button API Source JS URL
    */
    public function getPaymentButtonScriptApiSourceJs()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        $merchantId   = $this->getMerchantId();
        if (strcmp($checkoutMode, "prod") === 0) {
            return self::PROD_CHECKOUT_API_BASE_URL."/api/v1/merchants/".$merchantId."/script.js";
        } elseif (strcmp($checkoutMode, "stag") === 0) {
            return self::STAG_CHECKOUT_API_BASE_URL."/api/v1/merchants/".$merchantId."/script.js";
        } elseif (strcmp($checkoutMode, "dev") === 0) {
            return self::DEV_CHECKOUT_API_BASE_URL."/api/v1/merchants/".$merchantId."/script.js";
        }
    }

    /**
    * get Payment Button APP Source JS URL
    */
    public function getPaymentButtonMagentoAppSourceJs()
    {
        $checkoutMode = $this->getNofraudAdvanceListMode();
        if (strcmp($checkoutMode, "prod") === 0) {
            return self::PROD_PAYMENT_APP_BASE_URL . "payment-options/scripts/magento.js";
        } elseif (strcmp($checkoutMode, "stag") === 0) {
            return self::STAG_PAYMENT_APP_BASE_URL . "payment-options/scripts/magento.js";
        } elseif (strcmp($checkoutMode, "dev") === 0) {
            return self::DEV_PAYMENT_APP_BASE_URL . "payment-options/scripts/magento.js";
        }
    }
}

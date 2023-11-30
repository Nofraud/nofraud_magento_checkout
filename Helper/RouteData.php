<?php

namespace NoFraud\Checkout\Helper;

use Magento\Backend\Model\Session\Quote as adminQuote;
use Magento\Checkout\Model\Session;
use Magento\Config\Model\Config\Source\Enabledisable;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Pricing\Helper\Data as Price;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Route\Route\Model\Route\Merchant;
use Route\Route\Model\Route\Quote;
use Route\Route\Helper\Data as OriginalData;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;

class RouteData extends OriginalData
{
    const ROUTE_FEE = 'route_fee';
    const ROUTE_TAX_FEE = 'route_tax_fee';
    const ROUTE_IS_INSURED = 'route_is_insured';
    const CONFIG_XML_PATH = 'route/route/';
    const CONFIG_ENABLED = 'enabled';
    const MERCHANT_PUBLIC_TOKEN = 'merchant_public_token';
    const MERCHANT_SECRET_TOKEN = 'merchant_secret_token';
    const INSURANCE_LABEL = 'insurance_label';
    const INSURANCE_ENABLED_BY_DEFAULT = 'default_setting';
    const IS_TAXABLE = 'is_taxable';
    const INCLUDE_ORDER_THANK_YOU_PAGE_WIDGET = 'include_order_thank_you_page_widget';
    const TAX_CLASS = 'payment_tax_class';
    const DEBUG = 'debug';
    const ORDER_STATUS = 'order_status';
    const ORDER_STATUS_CANCELED = 'order_status_canceled';
    const MODULE_NAME = 'Route_Route';
    const TEST_PREFIX = 'test-';
    const ROUTE_LABEL = 'Route Shipping Protection';
    const DEFAULT_MAX_USD_SUBTOTAL_ALLOWED = 5000;
    const DEFAULT_MIN_USD_SUBTOTAL_ALLOWED = 0;
    const ADMIN_AREA_CODE = \Magento\Framework\App\Area::AREA_ADMINHTML;
    const BILLING_INFO_FILLED = 'billing_info';
    const EXCLUDED_SHIPPING_METHODS = 'excluded_shipping_methods';
    const EXCLUDED_PAYMENT_METHODS = 'excluded_payment_methods';
    const SHIPMENTS_CRONJOB_CUSTOM_PERIOD = 'resend_shipments_operations_custom';
    const LATEST_VERSION_CHECK_DATE = 'latest_version_check_date';
    const LATEST_VERSION_CHECK_VERSION = 'latest_version_check_version';
    const INVALID_MERCHANT = 'invalid_merchant';

    protected $checkoutSession;
    protected $productMetadata;
    protected $moduleList;
    protected $jsonFactory;
    protected $quoteClient;
    protected $storeManager;
    protected $price;
    protected $scopeCode;
    protected $configWriter;
    protected $quoteRepository;
    protected $state;
    protected $adminQuoteSession;
    protected $moduleManager;
    protected $orderRepository;
    protected $quoteFactory;

    private $merchantClient;
    private $collectionFactory;

    /**
     * Data constructor.
     *
     * @param Context $context Context variable
     * @param Session $checkoutSession Current checkout session
     * @param ProductMetadataInterface $productMetadata Magento application
     *                                                  product metadata
     * @param ModuleListInterface $moduleList Module list
     * @param Quote $quoteClient Route Quote Client
     * @param StoreManagerInterface $storeManager Store Manager
     * @param Price $price Price
     * @param Merchant $merchantClient Route Merchant client
     * @param CollectionFactory $collectionFactory Route Merchant client
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger Sentry logger
     * @param CartRepositoryInterface $quoteRepository
     * @param State $state
     * @param adminQuote $adminQuoteSession
     * @param Manager $moduleManager
     */
    public function __construct(
        Context                  $context,
        Session                  $checkoutSession,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface      $moduleList,
        Quote                    $quoteClient,
        StoreManagerInterface    $storeManager,
        Price                    $price,
        Merchant                 $merchantClient,
        CollectionFactory        $collectionFactory,
        WriterInterface          $configWriter,
        LoggerInterface          $logger,
        CartRepositoryInterface  $quoteRepository,
        State                    $state,
        adminQuote               $adminQuoteSession,
        Manager                  $moduleManager,
        OrderRepositoryInterface $orderRepository,
        QuoteFactory             $quoteFactory
    ) {
        $this->checkoutSession   = $checkoutSession;
        $this->productMetadata   = $productMetadata;
        $this->moduleList        = $moduleList;
        $this->quoteClient       = $quoteClient;
        $this->storeManager      = $storeManager;
        $this->price             = $price;
        $this->merchantClient    = $merchantClient;
        $this->collectionFactory = $collectionFactory;
        $this->configWriter      = $configWriter;
        $this->_logger           = $logger;
        $this->quoteRepository   = $quoteRepository;
        $this->state             = $state;
        $this->adminQuoteSession = $adminQuoteSession;
        $this->moduleManager     = $moduleManager;
        $this->orderRepository   = $orderRepository;
        $this->quoteFactory      = $quoteFactory;
        parent::__construct(
            $context,
            $checkoutSession,
            $productMetadata,
            $moduleList,
            $quoteClient,
            $storeManager,
            $price,
            $merchantClient,
            $collectionFactory,
            $configWriter,
            $logger,
            $quoteRepository,
            $state,
            $adminQuoteSession,
            $moduleManager
        );
    }

    /**
     * Generic method to retrieve Route Settings passing
     * the config name as param
     *
     * @param string $config config name
     * @param bool $escapeCache scape cache
     *
     * @return mixed
     */
    public function getConfigValue($config, $escapeCache = false)
    {
        if ($escapeCache) {
            return $this->getConfigValueByPassCache(
                self::CONFIG_XML_PATH . $config,
                ScopeInterface::SCOPE_STORES,
                $this->scopeCode
            );
        }

        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH . $config,
            ScopeInterface::SCOPE_STORES,
            $this->scopeCode
        );
    }

    /**
     * Save config
     *
     * @param $config
     * @param $value
     * @param $scope
     * @param int $scopeId
     */
    public function saveConfig($config, $value, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0)
    {
        $this->configWriter->save(self::CONFIG_XML_PATH . $config, $value, $scope, $scopeId);
    }

    /**
     * Generic method to retrieve Route Settings passing
     * the config name as param by passing cache
     * @param $config
     * @param $scope
     * @param $scopeId
     * @return mixed
     */
    public function getConfigValueByPassCache($config, $scope, $scopeId)
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('path', $config);
        foreach ($collection->getItems() as $config) {
            return $config->getValue();
        }
    }

    /**
     * Change default scope
     * @param $scopeCode
     */
    public function setScopeCode($scopeCode)
    {
        if (isset($scopeCode) && $scopeCode >= 0) {
            $this->scopeCode = $scopeCode;
        }
    }

    /**
     * Check if it has test token
     *
     * @return bool
     */
    public function hasTestSecretToken()
    {
        return substr($this->getMerchantSecretToken(), 0, strlen(self::TEST_PREFIX)) === self::TEST_PREFIX;
    }

    /**
     * Get current module version
     *
     * @return mixed
     */
    public function getVersion()
    {
        return $this->moduleList
            ->getOne(self::MODULE_NAME)['setup_version'];
    }

    /**
     * It returns merchant public token as string
     *
     * @return string
     */
    public function getMerchantPublicToken()
    {
        return $this->getConfigValue(self::MERCHANT_PUBLIC_TOKEN);
    }

    /**
     * It returns merchant secret token as string
     *
     * @return string
     */
    public function getMerchantSecretToken()
    {
        return $this->getConfigValue(self::MERCHANT_SECRET_TOKEN);
    }

    /**
     * It returns Route Fee label
     *
     * @return string
     */
    public function getRouteLabel()
    {
        return self::ROUTE_LABEL;
    }

    /**
     * Get order status to submit
     *
     * @return string
     */
    public function getOrderStatus()
    {
        return empty($this->getConfigValue(self::ORDER_STATUS)) ?
            $this->getConfigValue(self::ORDER_STATUS) :
            explode(',', $this->getConfigValue(self::ORDER_STATUS));
    }

    /**
     * Check if it can be canceled
     *
     * @param Order $order
     *
     * @return string
     */
    public function canCancelOrder($order)
    {
        return empty($this->getOrderStatusCanceled()) || in_array($order->getStatus(), $this->getOrderStatusCanceled());
    }

    /**
     * Get order canceled status to submit
     *
     * @return string
     */
    public function getOrderStatusCanceled()
    {
        return empty($this->getConfigValue(self::ORDER_STATUS_CANCELED)) ?
            $this->getConfigValue(self::ORDER_STATUS_CANCELED) :
            explode(',', $this->getConfigValue(self::ORDER_STATUS_CANCELED));
    }

    /**
     * Check if it can be submitted
     *
     * @param OrderInterface $order
     *
     * @return string
     */
    public function canSubmitOrder($order)
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }

        return empty($this->getOrderStatus()) ||
            is_null($this->getConfigValue(self::ORDER_STATUS)) ||
            in_array($order->getStatus(), $this->getOrderStatus());
    }

    /**
     * Check if it should add Route Fee to summaries
     *
     * @return bool
     */
    public function canAddRouteFee()
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }

        return $this->isAllowSubtotal() &&
            $this->isInsured() &&
            $this->isRoutePlus() &&
            !$this->isFullCoverage() &&
            $this->isRouteProtectionAllowed();
    }

    /**
     * It returns if the current session is insured
     *
     * @return string
     */
    public function isInsured()
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }

        $checkout = $this->isAdmin() ? $this->adminQuoteSession : $this->checkoutSession;

        return (bool)  ($this->isFullCoverage() ||
            ($this->merchantClient->isOptOut() && (is_null($checkout->getInsured()) || !!$checkout->getInsured())) ||
            ($this->merchantClient->isOptIn() && !!$checkout->getInsured())
        );
    }

    /**
     * Check if merchant is Route Plus
     *
     * @return bool Enabled or not
     */
    public function isFullCoverage()
    {
        return $this->merchantClient->isFullCoverage() && $this->isRoutePlus();
    }

    /**
     * Check if merchant is Route Plus
     *
     * @return bool
     */
    public function isRoutePlus()
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }

        return $this->merchantClient->isRoutePlus();
    }

    /**
     * It serializes param $data as json
     *
     * @param array $data array an array param is expected
     *
     * @return string
     */
    public function serialize($data)
    {
        return json_encode($data);
    }

    /**
     * It decodes param $json to array assoc
     *
     * @param string $json an string json is expected
     *
     * @return mixed
     */
    public function unserialize($json)
    {
        return json_decode($json, true);
    }

    /**
     * It returns the current Magento Version
     *
     * @return string
     */
    public function getMageVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * It adds a comment to the history of the order passed by param
     * before it verifies the Magento version
     *
     * @param Order $order Order to add the comment
     * @param string $comment the comment
     * @param bool $save save the comment
     *
     * @return void
     */
    public function addCommentToOrderHistory($order, $comment, $save = false)
    {
        $this->_logger->debug($comment);
    }

    /**
     * It checks if the configuration Default setting is set to Opt-in or Opt-out
     *
     * @return bool
     */
    public function isEnabledDefaultInsurance()
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }

        return (bool)!$this->merchantClient->isOptIn();
    }

    /**
     * Get Route Line
     *
     * @param string $format Desired format default php array
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getRouteLine($format = "")
    {
        $line = [
            'label' => $this->getRouteLabel(),
            'amount' => $this->_getCurrentQuote(),
            'currency' => $this->storeManager->getStore()->getCurrentCurrencyCode(),
            'currency_symbol' => $this->storeManager->getStore()->getBaseCurrency()->getCurrencySymbol()
        ];

        if ($format == 'json') {
            return $this->serialize(['route' => $line]);
        }

        if ($format == 'html') {
            return $this->_htmlFormatLine($line);
        }

        return $line;
    }

    /**
     * Get Route line as html
     *
     * @param $line
     *
     * @return string
     */
    private function _htmlFormatLine($line)
    {
        $amountFormatted = $this->price->currency($line['amount'], true, false);
        return "<div id=\"route\">" .
            "<span class=\"label\">{$line['label']}</span>" .
            "<span class=\"amount\">{$amountFormatted}</span>" .
            "</div>";
    }

    /**
     * Get Current Quote
     *
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function _getCurrentQuote()
    {
        $checkout = $this->isAdmin() ? $this->adminQuoteSession : $this->checkoutSession;
        $quote = $checkout->getQuote();
        $subtotal = $quote->getSubtotal() ? $quote->getSubtotal() : 0;
        $amountCovered = $this->getShippableItemsSubtotal($quote);
        $quote = $this->quoteClient->getQuote(
            $subtotal,
            $amountCovered,
            $this->isInsured(),
            $this->_getQuoteCurrency()
        );
        return is_array($quote) ? $quote['insurance_price'] : 0;
    }

    /**
     * Get Current Quote
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function _getQuoteCurrency()
    {
        $quote = $this->isAdmin() ? $this->adminQuoteSession->getQuote() : $this->checkoutSession->getQuote();
        return $quote->getQuoteCurrencyCode();
    }

    /**
     * Check if the Route Fee is Taxable
     *
     * @return bool
     */
    public function isTaxable()
    {
        return (bool)$this->getConfigValue(self::IS_TAXABLE);
    }

    /**
     * Check if the Route order thank you page is enabled
     *
     * @return bool
     */
    public function isIncludesOrderThankYouPageWidget()
    {
        return (bool) $this->getConfigValue(self::INCLUDE_ORDER_THANK_YOU_PAGE_WIDGET);
    }

    /**
     * Returns the tax class id from Route Fee
     *
     * @return int
     */
    public function getTaxClassId()
    {
        return $this->getConfigValue(self::TAX_CLASS);
    }

    /**
     * Returns if billing info was checked
     *
     * @return bool
     */
    public function isBillingChecked()
    {
        return !is_null($this->isBillingFilled());
    }

    /**
     * Returns shipment cronjob custom period
     *
     * @return int
     */
    public function getShipmentCronjobCustomPeriod()
    {
        return $this->getConfigValue(self::SHIPMENTS_CRONJOB_CUSTOM_PERIOD);
    }

    /**
     * Returns last time we perform a version check on admin page
     *
     * @return int
     */
    public function getLatestVersionCheckDate()
    {
        return $this->getConfigValue(self::LATEST_VERSION_CHECK_DATE);
    }

    /**
     * Returns version check on admin page
     *
     * @return int
     */
    public function getLatestVersionCheckVersion()
    {
        return $this->getConfigValue(self::LATEST_VERSION_CHECK_VERSION);
    }

    /**
     * Set billing info as checked or not
     *
     * @param $isFilled
     *
     * @return void
     */
    public function setBillingFilled($isFilled)
    {
        $this->saveConfig(self::BILLING_INFO_FILLED, intval($isFilled));
    }

    /**
     * Check flag if billing is filled
     *
     * @return mixed
     */
    public function isBillingFilled()
    {
        return $this->getConfigValue(self::BILLING_INFO_FILLED, true);
    }

    /**
     * Get current store Domain
     *
     * @return string
     */
    public function getCurrentStoreDomain()
    {
        try {
            return parse_url($this->getStoreUrl(), PHP_URL_HOST);
        } catch (\Exception $exception) {
            $this->_logger->error('Couldn\'t get store domain');
            return "";
        }
    }

    /**
     * Returns store URL
     *
     * @return string
     *
     * @throws NoSuchEntityException
     */
    public function getStoreUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * Check if Route debug is enable
     *
     * @return bool
     */
    public function isDebugEnable()
    {
        return false;
        //return (bool)$this->getConfigValue(self::DEBUG);
    }

    /**
     * Check cart subtotal, hide if subtotal above allowed
     * @return bool
     */
    public function isAllowSubtotal()
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }
        $quoteResponse = $this->getQuoteResponse();

        $currentUsdSubtotal = 0;
        if (isset($quoteResponse['subtotal_usd'])) {
            if ($quoteResponse['subtotal_usd'] > 0) {
                $currentUsdSubtotal = $quoteResponse['subtotal_usd'];
            }
        }
        $maxUsdSubtotal = self::DEFAULT_MAX_USD_SUBTOTAL_ALLOWED;
        if (isset($quoteResponse['coverage_upper_limit'])) {
            if ($quoteResponse['coverage_upper_limit'] > 0) {
                $maxUsdSubtotal = $quoteResponse['coverage_upper_limit'];
            }
        }
        $minUsdSubtotal = self::DEFAULT_MIN_USD_SUBTOTAL_ALLOWED;
        if (isset($quoteResponse['coverage_lower_limit'])) {
            if ($quoteResponse['coverage_lower_limit'] > 0) {
                $minUsdSubtotal = $quoteResponse['coverage_lower_limit'];
            }
        }
        return ($minUsdSubtotal < $currentUsdSubtotal) && ($currentUsdSubtotal < $maxUsdSubtotal);
    }

    /**
     * @return mixed
     */
    public function getQuoteResponse()
    {
        $quote = $this->_getQuote();

        if (!$quote) {
            return false;
        }

        $subtotal = $quote->getSubtotal() ? $quote->getSubtotal() : 0;
        $amountCovered = $this->getShippableItemsSubtotal($quote);
        if (!$subtotal || $subtotal == 0 || !$amountCovered || $amountCovered == 0) {
            return true;
        }
        return $this->quoteClient->getQuote(
            $subtotal,
            $amountCovered,
            $this->isInsured(),
            $quote->getQuoteCurrencyCode()
        );
    }

    private function _getQuote()
    {
        $quoteSession =  $this->isAdmin() ? $this->adminQuoteSession : $this->checkoutSession;
        $quoteId = $quoteSession->getQuoteId();
        if (!$quoteId) {
            try {
                return $quoteSession->getQuote();
            } catch (\Exception $exception) {
                return false;
            }
        }
        return $this->quoteRepository->get($quoteId);
    }

    public function isAdmin()
    {
        $areaCode = $this->state->getAreaCode();
        return $areaCode == self::ADMIN_AREA_CODE;
    }

    /**
     *  Get Quote/Order subtotal for shippable items only
     *getShippableItemsSubtotal
     * @param $obj Quote || Order
     * @param $total
     * @return float|int
     */
    public function getShippableItemsSubtotal($obj, $total = null)
    {
        if ($obj instanceof \Magento\Sales\Model\Order) {
            $obj = $this->orderRepository->get($obj->getId());
        } elseif ($obj instanceof \Magento\Quote\Model\Quote) {
            $obj = $this->quoteFactory->create()->load($obj->getId());
        }

        $subtotal = !empty($total) ? $total : $obj->getSubtotal();

        if ($subtotal == 0) {
            $subtotal = $obj->getShippingAddress()->getSubtotal();
        }

        $items = $obj->getAllItems();

        foreach ($items as $item) {
            if (!($item->getProduct() instanceof \Magento\Catalog\Model\Product)) {
                continue;
            }

            if ($item->getProduct()->getIsVirtual() || $item->getProduct()->getTypeId() == \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE) {
                $qty = $item->getQty() ? intval($item->getQty()) : intval($item->getQtyOrdered());
                $productPrice = floatval($item->getPrice()) * $qty;
                $subtotal = $subtotal - $productPrice;
            }
        }

        return $subtotal;
    }

    /**
     * Check if the shipping method allows Route to appear
     *
     * @param mixed $shippingMethod
     * @param mixed $paymentMethod
     * @return bool
     */
    public function isRouteProtectionAllowed($shippingMethod = false, $paymentMethod = false)
    {
        if (!$this->isRouteModuleEnable()) {
            return false;
        }

        $quote = $this->_getQuote();

        if (!$quote) {
            return false;
        }

        if (!$shippingMethod) {
            $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
        }

        if (!$paymentMethod) {
            $paymentMethod = $quote->getPayment()->getMethod();
        }

        $excludedShippingMethods = $this->getExcludedShippingMethods();
        $excludedPaymentMethods = $this->getExcludedPaymentMethods();

        return (!$shippingMethod || !in_array($shippingMethod, $excludedShippingMethods)) &&
            (!$paymentMethod || !in_array($paymentMethod, $excludedPaymentMethods));
    }

    /**
     * Get excluded shipping methods
     *
     * @return array
     */
    public function getExcludedShippingMethods()
    {
        return empty($this->getConfigValue(self::EXCLUDED_SHIPPING_METHODS)) ?
            [] :
            explode(',', $this->getConfigValue(self::EXCLUDED_SHIPPING_METHODS));
    }

    /**
     * Get excluded payment methods
     *
     * @return array
     */
    public function getExcludedPaymentMethods()
    {
        return empty($this->getConfigValue(self::EXCLUDED_PAYMENT_METHODS)) ?
            [] :
            explode(',', $this->getConfigValue(self::EXCLUDED_PAYMENT_METHODS));
    }

    /**
     * Check if it's has valid Merchants
     *
     * @return bool
     */
    public function hasValidMerchantTokens()
    {
        return $this->hasMerchantTokens() && !empty($this->merchantClient->getMerchant());
    }

    /**
     * Check if it's has Merchant Tokens
     *
     * @return bool
     */
    private function hasMerchantTokens()
    {
        return !empty($this->getMerchantPublicToken()) && !empty($this->getMerchantSecretToken());
    }

    /**
     * Set current configuration as invalid merchant
     */
    public function setMerchantAsInvalid()
    {
        $this->saveConfig(self::INVALID_MERCHANT, true);
    }

    /**
     * Remove merchant invalid flag
     */
    public function removeMerchantInvalidFlag()
    {
        $this->saveConfig(self::INVALID_MERCHANT, false);
    }

    /**
     * Check if it's a invalid merchant
     * @return bool
     */
    public function isInvalidMerchant()
    {
        $invalidMerchantConfig = $this->getConfigValue(self::INVALID_MERCHANT, true);
        return !empty($invalidMerchantConfig) || $invalidMerchantConfig;
    }

    /**
     * Check if Route Extension is Enabled
     * @return bool
     */
    public function isRouteModuleEnable()
    {
        $isRouteModuleEnable = $this->getConfigValue(self::CONFIG_ENABLED);
        return $isRouteModuleEnable == Enabledisable::ENABLE_VALUE;
    }

    /**
     * Check if module is enabled
     * @param $moduleName
     * @return bool
     */
    public function isModuleEnabled($moduleName)
    {
        return $this->moduleManager->isEnabled($moduleName);
    }
}

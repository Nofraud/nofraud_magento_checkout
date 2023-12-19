<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\SetConfiguration;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\PageCache\Version;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\ResourceConnection;
use NoFraud\Checkout\Helper\Data as DataHelper;

class SetConfigurationData implements SetConfiguration
{
    protected $logger;

    protected $configWriter;

    protected $cacheTypeList;

    protected $cacheFrontendPool;

    protected $resourceConnection;

    const ENABLED     = "nofraud/general/enabled";

    const MERCHANT_Id = "nofraud/general/merchant";

    const API_KEY     = "nofraud/general/api_key";

    const NF_TOKEN    = "nofraud/general/nf_token";

    public function __construct(
        WriterInterface    $configWriter,
        TypeListInterface  $cacheTypeList,
        Pool               $cacheFrontendPool,
        ResourceConnection $resourceConnection
    ) {
        $this->configWriter       = $configWriter;
        $this->cacheTypeList      = $cacheTypeList;
        $this->cacheFrontendPool  = $cacheFrontendPool;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param $path
     * @param $value
     */
    public function SetData($path, $value)
    {
        $this->configWriter->save($path, $value, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
    }

    public function enableConfiguration($data)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/enableConfiguration.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('enableConfiguration');
        $logger->info(json_encode($data));

        $connection      = $this->resourceConnection->getConnection();
        $NFCheckoutTable = $this->resourceConnection->getTableName('nofraud_checkout');

        $enabled                  = $data["enabled"];
        $merchant_id              = $data["merchant_id"];
        $nf_token                 = $data["nf_token"];
        $checkoutApiBaseUrl       = $data["checkout_api_base_url"];
        $jsBaseUrl                = $data['js_base_url'];
        $cancelTransactionBaseUrl = $data['cancel_transaction_base_url'];
        $nfApiStatusBaseUrl       = $data["nf_api_status_base_url"];
        $paymentAppBaseUrl        = $data["payment_app_base_url"];

        if (isset($enabled) && isset($merchant_id) && isset($nf_token) && isset($checkoutApiBaseUrl) && isset($jsBaseUrl) && isset($cancelTransactionBaseUrl) && isset($nfApiStatusBaseUrl) && isset($paymentAppBaseUrl)) {
            try {
                if ($enabled == true) {
                    $this->SetData(self::ENABLED, 1);
                } else {
                    $this->SetData(self::ENABLED, 0);
                }
                $this->SetData(self::MERCHANT_Id, $merchant_id);
                $this->SetData(self::NF_TOKEN, $nf_token);

                $checkoutBaseUrlFetchQuery  = "SELECT data_value FROM `" . $NFCheckoutTable . "` WHERE `data_key` =  '" . DataHelper::CHECKOUT_API_BASE_URL_KEY . "'";
                $checkoutBaseUrlSelectQuery = $connection->fetchAll($checkoutBaseUrlFetchQuery);
                if ($checkoutBaseUrlSelectQuery) {
                    $checkoutBaseUrlQuery = "update " . $NFCheckoutTable . " set data_value = '" . $checkoutApiBaseUrl . "' WHERE `data_key` = '" . DataHelper::CHECKOUT_API_BASE_URL_KEY . "'";
                    $connection->query($checkoutBaseUrlQuery);
                } else {
                    $checkoutBaseUrlQuery = "Insert Into " . $NFCheckoutTable . " (data_key,data_value) Values ('" . DataHelper::CHECKOUT_API_BASE_URL_KEY . "','" . $checkoutApiBaseUrl . "')";
                    $connection->query($checkoutBaseUrlQuery);
                }

                $jsBaseUrlFetchQuery  = "SELECT data_value FROM `" . $NFCheckoutTable . "` WHERE `data_key` = '" . DataHelper::API_SOURCE_JS_KEY . "'";
                $jsBaseUrlSelectQuery = $connection->fetchAll($jsBaseUrlFetchQuery);
                if ($jsBaseUrlSelectQuery) {
                    $jsBaseUrlQuery = "update " . $NFCheckoutTable . " set data_value = '" . $jsBaseUrl . "' WHERE `data_key` = '" . DataHelper::API_SOURCE_JS_KEY . "'";
                    $connection->query($jsBaseUrlQuery);
                } else {
                    $jsBaseUrlQuery = "Insert Into " . $NFCheckoutTable . " (data_key,data_value) Values ('" . DataHelper::API_SOURCE_JS_KEY . "','" . $jsBaseUrl . "')";
                    $connection->query($jsBaseUrlQuery);
                }

                $cancelTransactionBaseUrlFetchQuery  = "SELECT data_value FROM `" . $NFCheckoutTable . "` WHERE `data_key` = '" . DataHelper::PORTAL_CANCEL_BASE_URL_KEY . "'";
                $cancelTransactionBaseUrlSelectQuery = $connection->fetchAll($cancelTransactionBaseUrlFetchQuery);
                if ($cancelTransactionBaseUrlSelectQuery) {
                    $cancelTransactionBaseUrlQuery = "update " . $NFCheckoutTable . " set data_value = '" . $cancelTransactionBaseUrl . "' WHERE `data_key` = '" . DataHelper::PORTAL_CANCEL_BASE_URL_KEY . "'";
                    $connection->query($cancelTransactionBaseUrlQuery);
                } else {
                    $cancelTransactionBaseUrlQuery = "Insert Into " . $NFCheckoutTable . " (data_key,data_value) Values ('" . DataHelper::PORTAL_CANCEL_BASE_URL_KEY . "','" . $cancelTransactionBaseUrl . "')";
                    $connection->query($cancelTransactionBaseUrlQuery);
                }

                $nfApiStatusBaseUrlFetchQuery  = "SELECT data_value FROM `" . $NFCheckoutTable . "` WHERE `data_key` = '" . DataHelper::NFAPI_STATUS_BASE_URL_KEY . "'";
                $nfApiStatusBaseUrlSelectQuery = $connection->fetchAll($nfApiStatusBaseUrlFetchQuery);
                if ($nfApiStatusBaseUrlSelectQuery) {
                    $nfApiStatusBaseUrlQuery = "update " . $NFCheckoutTable . " set data_value = '" . $nfApiStatusBaseUrl . "' WHERE `data_key` = '" . DataHelper::NFAPI_STATUS_BASE_URL_KEY . "'";
                    $connection->query($nfApiStatusBaseUrlQuery);
                } else {
                    $nfApiStatusBaseUrlQuery = "Insert Into " . $NFCheckoutTable . " (data_key,data_value) Values ('" . DataHelper::NFAPI_STATUS_BASE_URL_KEY . "','" . $nfApiStatusBaseUrl . "')";
                    $connection->query($nfApiStatusBaseUrlQuery);
                }

                $paymentAppBaseUrlFetchQuery  = "SELECT data_value FROM `" . $NFCheckoutTable . "` WHERE `data_key` = '" . DataHelper::PAYMENT_APP_BASE_URL_KEY . "'";
                $paymentAppBaseUrlSelectQuery = $connection->fetchAll($paymentAppBaseUrlFetchQuery);
                if ($paymentAppBaseUrlSelectQuery) {
                    $paymentAppBaseUrlQuery = "update " . $NFCheckoutTable . " set data_value = '" . $paymentAppBaseUrl . "' WHERE `data_key` = '" . DataHelper::PAYMENT_APP_BASE_URL_KEY . "'";
                    $connection->query($paymentAppBaseUrlQuery);
                } else {
                    $paymentAppBaseUrlQuery = "Insert Into " . $NFCheckoutTable . " (data_key,data_value) Values ('" . DataHelper::PAYMENT_APP_BASE_URL_KEY . "','" . $paymentAppBaseUrl . "')";
                    $connection->query($paymentAppBaseUrlQuery);
                }

                $this->flushCache();
                $response = [
                    [
                        "code" => 'success',
                        "message" => 'All fields updated successfully !',
                    ],
                ];
            } catch (\Exception $e) {
                $response = [
                    [
                        "code" => 'error',
                        "message" => $e->getMessage(),
                    ],
                ];
            }
        } else {
            $response = [
                [
                    "code" => 'error',
                    "message" => 'Missing or invalid value.',
                ],
            ];
        }
        return $response;
    }

    public function flushCache()
    {
        $_types = [
            'config',
            'layout',
            'block_html',
            'collections',
            'reflection',
            'db_ddl',
            'eav',
            'config_integration',
            'config_integration_api',
            'full_page',
            'translate',
            'config_webservice'
        ];

        foreach ($_types as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}

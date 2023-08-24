<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\UpdateTransactionResultInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\PageCache\Version;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Sales\Model\Order;
use Magento\Framework\Serialize\Serializer\Json;

class UpdateTransactionResult implements UpdateTransactionResultInterface
{
    protected $logger;

    protected $configWriter;

    protected $cacheTypeList;

    protected $cacheFrontendPool;

    protected $orderRepository;

    protected $stateIndex = [];

    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'fraud',
        'error',
        'fraudulent',
    ];

    private   $orders;

    private   $storeManager;

    public function __construct(
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        \NoFraud\Checkout\Helper\Data $dataHelper,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditMemoFacory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->configWriter             = $configWriter;
        $this->cacheTypeList            = $cacheTypeList;
        $this->cacheFrontendPool        = $cacheFrontendPool;
        $this->orderRepository          = $orderRepository;
        $this->orderStatusCollection    = $orderStatusCollection;
        $this->dataHelper               = $dataHelper;
        $this->invoice                  = $invoice;
        $this->creditMemoFacory         = $creditMemoFacory;
        $this->creditmemoService        = $creditmemoService;
        $this->orderInterface           = $orderInterface;
        $this->orders                   = $orderCollectionFactory;
        $this->storeManager             = $storeManager;
    }

    public function updateTransactionResult($data)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/nf_order_status.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('Nf order status update start');
        $logger->info(json_encode($data));

        $nfOrderStatus   = $data["status"];
        $nfTransactionId = $data["transaction_id"];

        if (isset($nfTransactionId) && isset($nfOrderStatus)) {
            try {
                $storeList = $this->storeManager->getStores();
                foreach ($storeList as $store) {
                    $storeId = $store->getId();
                    $orders  = $this->readOrders($storeId, $logger, $nfTransactionId);
                    if (count($orders)) {
                        foreach ($orders as $order) {
                            $this->updateOrdersFromNoFraudApiResult($order, $nfOrderStatus, $logger);
                            $this->flushCache();
                            $response = [
                                [
                                    "code" => 'success',
                                    "message" => 'Payment mode updated successfully !',
                                ],
                            ];
                            return $response;
                        }
                    } else {
                        $response = [
                            [
                                "code" => 'error',
                                "message" => 'TransactionId: ' . $nfTransactionId . ' not found',
                            ],
                        ];
                    }
                }
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
                    "message" => 'Missing Data : status || transaction_id',
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

    public function getStateFromStatus($state)
    {
        $statuses = $this->orderStatusCollection->create()->joinStates();
        if (empty($this->stateIndex)) {
            foreach ($statuses as $status) {
                $this->stateIndex[$status->getStatus()] = $status->getState();
            }
        }
        return $this->stateIndex[$state] ?? null;
    }

    /**
     * get payment Transaction Id
     */
    private function makeRefund($order)
    {
        $nofraudcheckout = $order->getData("nofraudcheckout");
        if (!$nofraudcheckout) {
            return false;
        }
        $nofraudcheckoutArray = json_decode($nofraudcheckout, true);
        if (isset($nofraudcheckoutArray["transaction_id"])) {
            $transaction_id = explode("#", $nofraudcheckoutArray["transaction_id"]);
            $transactionId = $transaction_id[0] ?? "";
            if (!empty($transactionId) && $transactionId != "") {
                $data = [
                    "authId" => $transactionId,
                    "amount" => round($order->getGrandTotal(), 2)
                ];
                $refundResponse = $this->initiateNofraudRefund($data);
                return $refundResponse;
            }
        }
        return false;
    }

    /**
     * Refund from NoFraud
     */
    private function initiateNofraudRefund($data)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/nf_order_status_cron_refund-api-' . date("d-m-Y") . '.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $logger->info("== Request information ==");
        $logger->info(print_r($data, true));

        $returnsFund = [];
        $returnsFund = ["success" => false];

        $apiUrl = $this->dataHelper->getRefundApiUrl();
        $nfToken = $this->dataHelper->getNofrudCheckoutAppNfToken();
        $logger->info($apiUrl);

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "x-nf-api-token: {$nfToken}"
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $logger->info("== Response Information ==");
            $logger->info(print_r($response, true));
            return $response;
        } catch (\Exception $e) {
            $returnsRefund = ["error_message" => $e->getMessage(), "success" => false];
        }
        return $returnsRefund;
    }

    private function getNofraudSettings($logger)
    {
        $nfToken    = $this->dataHelper->getNofrudCheckoutAppNfToken();
        $merchantId = $this->dataHelper->getMerchantId();
        $apiUrl     = $this->dataHelper->getNofraudMerSettings() . $merchantId;
        $logger->info("\n" . $apiUrl);
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "x-nf-api-token:{$nfToken}"
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $responseArray = json_decode($response, true);
            return $responseArray;
        } catch (\Exception $e) {
            $logger->info("\n" . $e->getMessage());
        }
    }

    /**
     *  create a credit memo
     */
    private function createCreditMemo($orderId)
    {
        try {
            $order      = $this->orderInterface->load($orderId);
            $invoices   = $order->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                $invoiceIncrementid = $invoice->getIncrementId();
                $invoiceInstance = $this->invoice->loadByIncrementId($invoiceIncrementid);
                $creditmemo = $this->creditMemoFacory->createByOrder($order);
                $creditmemo->setInvoice($invoiceInstance);
                $this->creditmemoService->refund($creditmemo);
                error_log("\n CreditMemo Succesfully Created For Order: " . $invoiceIncrementid, 3, BP . "/var/log/cron_credit.log");
            }
        } catch (\Exception $e) {
            error_log("\n Creditmemo Not Created" . $e->getMessage(), 3, BP . "/var/log/cron_credit.log");
        }
    }

    public function readOrders($storeId, $logger, $transactionId)
    {
        $orders = $this->orders->create()
            ->addFieldToSelect('status')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('entity_id')
            ->addFieldToSelect('nofraud_checkout_status')
            ->addFieldToSelect('nofraud_checkout_screened')
            ->addFieldToSelect('nofraudcheckout')
            ->addFieldToSelect('grand_total')
            ->addFieldToFilter('nofraud_transaction_id', $transactionId)
            ->setOrder('status', 'desc');

        $select = $orders->getSelect()
            ->where('store_id = ' . $storeId)
            ->where('nofraud_checkout_screened != 1 OR nofraud_checkout_status = \'review\'')
            ->where('status = \'processing\' OR status = \'pending_payment\' OR status = \'payment_review\' OR nofraud_checkout_status = \'review\'');
        $logger->info($orders->getSelect());
        return $orders;
    }

    public function updateOrdersFromNoFraudApiResult($order, $nfOrderStatus, $logger)
    {
        if ($order->getPayment()->getMethod() == 'nofraud') {
            $merchantPreferences = $this->getNofraudSettings($logger);
            $settings            = $merchantPreferences['platform']['settings'];
            $manualCapture       = $merchantPreferences['settings']['manualCapture']['isEnabled'];
            $order->setNofraudCheckoutScreened(true);
            if (isset($nfOrderStatus)) {
                $statusName     = $nfOrderStatus;
                $noFraudStatus  = $nfOrderStatus;
            } else {
                $statusName = 'error';
                $noFraudStatus = 'error';
            }
            //
            if (in_array($noFraudStatus, $this->orderStatusesKeys)) {
                $orderRefundedInNofraud = false;
                if ($noFraudStatus == "pass") {
                    error_log("\n inside " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                    $newStatus  =  $settings['passOrderStatus'];
                    $newState   = $this->getStateFromStatus($newStatus);
                    error_log("\n status " . $newStatus . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                    error_log("\n state " . $newState . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                    $order->setStatus($newStatus)->setState($newState);
                    $order->setNofraudCheckoutStatus($noFraudStatus);
                    $order->addStatusHistoryComment(__('NoFraud updated order status to ' .$noFraudStatus), false);
                    $order->save();
                } else if ($noFraudStatus == "fail" || $noFraudStatus == "fraudulent" || $noFraudStatus == "fraud") {
                    if (isset($settings['shouldAutoRefund']) && (empty($manualCapture) || $manualCapture == false)) {
                        $refundResponse = $this->makeRefund($order);
                        error_log("\n inside " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                        error_log("\n res " . print_r($refundResponse, true) . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                        if ($refundResponse) {
                            $responseArray = json_decode($refundResponse, true);
                            if ($responseArray && isset($responseArray["success"]) && $responseArray["success"] == true) {
                                error_log("\n success " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                                $order->setNofraudCheckoutStatus($noFraudStatus);
                                $order->addStatusHistoryComment(__('NoFraud updated order status to ' .$noFraudStatus), false);
                                $this->createCreditMemo($order->getId());
                                $orderRefundedInNofraud = true;
                                $updateOrder      = true;
                            } else if ($responseArray && isset($responseArray["errorMsg"])) {
                                error_log("\n No Response from API endpoint " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                                //continue;
                            }
                        } else {
                            error_log("\n No Response from API endpoint " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status.log");
                            //continue;
                        }
                    }
                    if (isset($settings['shouldAutoCancel'])) {
                        if (isset($settings['shouldAutoRefund']) && $settings['shouldAutoRefund'] == true && $orderRefundedInNofraud == true) {
                            error_log("\n inside " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status_cancel.log");
                            $newState = Order::STATE_CANCELED;
                            $order->cancel();
                            $order->setStatus($newState)->setState($newState);
                            error_log("\n state " . $newState . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status_cancel.log");
                        }
                        if (empty($settings['shouldAutoRefund']) || $settings['shouldAutoRefund'] == false) {
                            error_log("\n inside " . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status_cancel.log");
                            $newState = Order::STATE_CANCELED;
                            $order->cancel();
                            $order->setStatus($newState)->setState($newState);
                            error_log("\n state " . $newState . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status_cancel.log");
                        }
                    }
                    $order->setNofraudCheckoutStatus($noFraudStatus);
                    $order->addStatusHistoryComment(__('NoFraud updated order status to ' .$noFraudStatus), false);
                    $order->save();
                } else if ($noFraudStatus == "review") {
                    $newStatus  =  "Pending Payment";
                    $newState   =  "payment_review";
                    error_log("\n status " . $newStatus . " <=> " . $order->getId(), 3, BP . "/var/log/nf_order_status_review.log");
                    //$order->setStatus($newStatus)->setState($newState);
                    $order->setNofraudCheckoutStatus($noFraudStatus);
                    $order->addStatusHistoryComment(__('NoFraud updated order status to ' .$noFraudStatus), false);
                    $order->save();
                }
            }
        }
    }
}

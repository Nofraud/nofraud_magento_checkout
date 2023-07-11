<?php

namespace NoFraud\Checkout\Cron;

use Magento\Sales\Model\Order;
use Magento\Framework\Serialize\Serializer\Json;

class OrderFraudStatus
{
    const ORDER_REQUEST = 'status';

    const REQUEST_TYPE  = 'GET';

    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'fraud',
        'error',
        'fraudulent',
    ];

    private $orders;

    private $storeManager;

    private $configHelper;

    private $moduleManager;

    protected $_logger;

    /**
     * @var StateIndex
     */
    protected $stateIndex = [];

    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \NoFraud\Checkout\Helper\Data $dataHelper,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditMemoFacory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface
    ) {
        $this->orders                   = $orders;
        $this->orderStatusCollection    = $orderStatusCollection;
        $this->storeManager             = $storeManager;
        $this->dataHelper               = $dataHelper;
        $this->moduleManager            = $moduleManager;
        $this->invoice                  = $invoice;
        $this->creditMemoFacory         = $creditMemoFacory;
        $this->creditmemoService        = $creditmemoService;
        $this->orderInterface           = $orderInterface;
    }

    public function execute()
    {
        $storeList = $this->storeManager->getStores();
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/NofraudCheckout.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $merchantPreferences = $this->getNofraudSettings($logger);
        $settings            = $merchantPreferences['platform']['settings'];
        $manualCapture       = $merchantPreferences['settings']['manualCapture']['isEnabled'];
        error_log(print_r($merchantPreferences['settings']['manualCapture'],true),3,BP."/var/log/settings.log");
        foreach ($storeList as $store) {
            $storeId = $store->getId();
            if (!$this->dataHelper->getEnabled($storeId)) {
                return;
            }
            $orders = $this->readOrders($storeId, $logger);
            $this->updateOrdersFromNoFraudApiResult($orders, $storeId, $logger,$settings,$manualCapture);
        }
    }

    public function readOrders($storeId, $logger)
    {
        $orders = $this->orders->create()
            ->addFieldToSelect('status')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('entity_id')
            ->addFieldToSelect('nofraud_checkout_status')
            ->addFieldToSelect('nofraud_checkout_screened')
            ->addFieldToSelect('nofraudcheckout')
            ->addFieldToSelect('grand_total')
            ->setOrder('status', 'desc');

        $select = $orders->getSelect()
            ->where('store_id = '.$storeId)
            ->where('nofraud_checkout_screened != 1 OR nofraud_checkout_status = \'review\'')
            ->where('status = \'processing\' OR status = \'pending_payment\' OR status = \'payment_review\' OR nofraud_checkout_status = \'review\'');
        $logger->info($orders->getSelect());
        return $orders;
    }

    public function getRefundTransactionId($order) {
        $nofraudcheckout = $order->getData("nofraudcheckout");
        error_log("\n nofraudcheckout ".$nofraudcheckout,3,BP."/var/log/checkoutlog.log");
        if(!$nofraudcheckout) {
            return false;
        }
        $nofraudcheckoutArray = json_decode($nofraudcheckout, true);
        if(isset($nofraudcheckoutArray["transaction_id"])) {
            $transaction_id = explode("#",$nofraudcheckoutArray["transaction_id"]);
            return $transaction_id[1] ?? "";
        }
        return false;
    }

    private function updateTransactionStatus($transaction_id, $logger) {
        $nfToken    = $this->dataHelper->getNofrudCheckoutAppNfToken();
        $apiUrl     = $this->dataHelper->getStatusByUrlApiUrl().$nfToken."/".$transaction_id;
        $logger->info("\n".$apiUrl);
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
                    "Content-Type: application/json"
                ),
            ));
            $logger->info("\n before call");
            $response = curl_exec($curl);
            curl_close($curl);
            $responseArray = json_decode($response, true);
            $logger->info("\n Nofraud response :".print_r($responseArray,true));
            $logger->info("\n after call");
            return $responseArray;
        } catch(\Exception $e) {
            $logger->info("\n".$e->getMessage());
        }
    }

    private function getNofraudSettings($logger) {
        $nfToken    = $this->dataHelper->getNofrudCheckoutAppNfToken();
        $merchantId = $this->dataHelper->getMerchantId();
        $apiUrl     = $this->dataHelper->getNofraudMerSettings().$merchantId;
        $logger->info("\n".$apiUrl);
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
        } catch(\Exception $e) {
            $logger->info("\n".$e->getMessage());
        }
    }

    public function updateOrdersFromNoFraudApiResult($orders, $storeId, $logger, $settings, $manualCapture)
    {
        foreach ($orders as $order) {
            try {
                if ($order && $order->getPayment()->getMethod() == 'nofraud') {
                    $transactionId = $this->getRefundTransactionId($order);
                    $logger->info("\n transactionId ".$transactionId." <=> ".$order->getId());
                    if($transactionId !== false){
                        $logger->info("\n Found transactionId ".$transactionId);
                        $nofraudCheckoutResponse = $this->updateTransactionStatus($transactionId, $logger);
                        $logger->info("\n inside transactionId ".print_r($nofraudCheckoutResponse,true)); 
                        if ( $nofraudCheckoutResponse && isset($nofraudCheckoutResponse["Errors"]) ){
                            continue;
                        } elseif ( $nofraudCheckoutResponse && isset($nofraudCheckoutResponse["decision"]) ){
                            $order->setNofraudCheckoutScreened(true);
                            if (isset($nofraudCheckoutResponse['decision'])) {
                                $statusName = $nofraudCheckoutResponse['decision'];
                                $noFraudStatus = $nofraudCheckoutResponse['decision'];
                            }else{
                                $statusName = 'error';
                                $noFraudStatus = 'error';
                            }
                            error_log("\n status Name ".$statusName." <=> ".$order->getId(),3,BP."/var/log/NFPstatus.log");
                            error_log("\n status settings ".print_r($settings,true)." <=> ".$order->getId(),3,BP."/var/log/NFPstatus.log");
                            if (in_array($noFraudStatus,$this->orderStatusesKeys)) {
                                $orderRefundedInNofraud = false;
                                if ($noFraudStatus == "pass") {
                                    error_log("\n inside "." <=> ".$order->getId(),3,BP."/var/log/cron_pass.log");
                                    $newStatus  =  $settings['passOrderStatus'];
                                    $newState   = $this->getStateFromStatus($newStatus);
                                    error_log("\n status ".$newStatus." <=> ".$order->getId(),3,BP."/var/log/cron_pass.log");
                                    error_log("\n state ".$newState." <=> ".$order->getId(),3,BP."/var/log/cron_pass.log");
                                    $order->setStatus($newStatus)->setState($newState);
                                    $order->setNofraudCheckoutStatus($noFraudStatus);
                                    $order->save();
                                } else if ( $noFraudStatus == "fail" || $noFraudStatus == "fraudulent" || $noFraudStatus == "fraud" ) {
                                    if ( isset($settings['shouldAutoRefund']) && (empty($manualCapture) || $manualCapture == false) ) {
                                        $refundResponse = $this->makeRefund($order);
                                        error_log("\n inside "." <=> ".$order->getId(),3,BP."/var/log/cron_refund.log");
                                        error_log("\n res ".print_r($refundResponse,true)." <=> ".$order->getId(),3,BP."/var/log/cron_refund.log");
                                        if($refundResponse) {
                                            $responseArray = json_decode($refundResponse, true);
                                            if($responseArray && isset($responseArray["success"]) && $responseArray["success"] == true) {
                                                error_log("\n success "." <=> ".$order->getId(),3,BP."/var/log/cron_refund.log");
                                                $order->setNofraudCheckoutStatus($noFraudStatus);
                                                $this->createCreditMemo($order->getId());
                                                $orderRefundedInNofraud = true;
                                                $updateOrder      = true;
                                            } else if($responseArray && isset($responseArray["errorMsg"])) {
                                                continue;
                                            }
                                        } else {
                                            error_log("\n No Response from API endpoint "." <=> ".$order->getId(),3,BP."/var/log/cron_refund.log");
                                            continue;
                                        }
                                    }
                                    if (isset($settings['shouldAutoCancel'])) {
                                        if (isset($settings['shouldAutoRefund']) && $settings['shouldAutoRefund'] == true && $orderRefundedInNofraud == true) {
                                            error_log("\n inside " . " <=> " . $order->getId(), 3, BP . "/var/log/cron_cancel.log");
                                            $newState = Order::STATE_CANCELED;
                                            $order->cancel();
                                            $order->setStatus($newState)->setState($newState);
                                            error_log("\n state " . $newState . " <=> " . $order->getId(), 3, BP . "/var/log/cron_cancel.log");
                                        }
                                        if (empty($settings['shouldAutoRefund']) || $settings['shouldAutoRefund'] == false) {
                                            error_log("\n inside " . " <=> " . $order->getId(), 3, BP . "/var/log/cron_cancel.log");
                                            $newState = Order::STATE_CANCELED;
                                            $order->cancel();
                                            $order->setStatus($newState)->setState($newState);
                                            error_log("\n state " . $newState . " <=> " . $order->getId(), 3, BP . "/var/log/cron_cancel.log");
                                        }
                                    }
                                    $order->setNofraudCheckoutStatus($noFraudStatus);
                                    $order->save();
                                } else if ($noFraudStatus == "review") {
                                    $newStatus  =  "Pending Payment";
                                    $newState   =  "payment_review";
                                    error_log("\n status ".$newStatus." <=> ".$order->getId(),3,BP."/var/log/cron_review.log");
                                    //$order->setStatus($newStatus)->setState($newState);
                                    $order->setNofraudCheckoutStatus($noFraudStatus);
                                    $order->save();
                                }
                            }
                            error_log("\n Last save ".$noFraudStatus." <=> ".$order->getId(),3,BP."/var/log/NFPstatus.log");
                        }
                    }
                }
            } catch (\Exception $exception) {
                $logger->info("Error for Order#".$order['increment_id']);
                $logger->info($exception->getMessage());
            }
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
    private function makeRefund($order) {
        $nofraudcheckout = $order->getData("nofraudcheckout");
        if(!$nofraudcheckout) {
            return false;
        }
        $nofraudcheckoutArray = json_decode($nofraudcheckout, true);
        if(isset($nofraudcheckoutArray["transaction_id"])) {
            $transaction_id = explode("#",$nofraudcheckoutArray["transaction_id"]);
            $transactionId = $transaction_id[0] ?? "";
            if (!empty($transactionId) && $transactionId != "") {
                $data = [
                    "authId" => $transactionId,
                    "amount" => round($order->getGrandTotal(),2)
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
    private function initiateNofraudRefund($data) {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron_refund-api-'.date("d-m-Y").'.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $logger->info("== Request information ==");
        $logger->info(print_r($data, true));

        $returnsFund = [];
        $returnsFund = ["success" => false];

        $apiUrl = $this->dataHelper->getRefundApiUrl();
        $nfToken= $this->dataHelper->getNofrudCheckoutAppNfToken();
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
            $logger->info(print_r($response,true));
            return $response;
        } catch(\Exception $e) {
            $returnsRefund = ["error_message" => $e->getMessage(), "success" => false];
        }
        return $returnsRefund;
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
            error_log("\n Creditmemo Not Created". $e->getMessage(),3,BP."/var/log/cron_credit.log");
        }
    }


}

<?php

namespace NoFraud\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;

use Magento\Framework\Event\ManagerInterface as EventManager;

class OrderObserver implements ObserverInterface
{
    const XML_PATH_ENABLED = 'nofraud/general/enabled';

    const XML_PATH_PAYMENT_ACTION = 'nofraud/advance/payment_action';

    private $_eventManager;

    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $_invoiceCollectionFactory;

    /**
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    protected $_invoiceRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        EventManager $eventManager,
        \Magento\Framework\Webapi\Rest\Request $restApiRequest,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \NoFraud\Checkout\Helper\Data $dataHelper
    ) {
        $this->_eventManager = $eventManager;
        $this->restApiRequest = $restApiRequest;
        $this->scopeConfig = $scopeConfig;
        $this->dataHelper  = $dataHelper;
        $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->_invoiceRepository = $invoiceRepository;
        $this->_orderRepository = $orderRepository;
        $this->_checkoutSession = $checkoutSession;
    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }


    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $isNofraudenabled = (int) $this->getConfig(self::XML_PATH_ENABLED);
      
        if ($isNofraudenabled && strpos($this->restApiRequest->getRequestUri(),"/order") !== false) {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/custom.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);

            $order = $observer->getEvent()->getOrder();
            $orderId = $order->getId();

            $this->_eventManager->dispatch(
                'nofraud_order_place_after',
                [
                    'order' => $order
                ]
            );

            $this->_eventManager->dispatch(
                'nofraud_checkout_success_action',
                [
                    'order_ids' => [$this->_checkoutSession->getLastOrderId()],
                    'order' => $order
                ]
            );
            $logger->info('observer for order : ' . $orderId);

            /* $paymentActions = $this->getConfig(self::XML_PATH_PAYMENT_ACTION);
            if($paymentActions == "authorize_capture"){
                $this->createInvoice($orderId);
            } */

            $merchantPreferences = $this->getNofraudSettings();
            $manualCapture       = $merchantPreferences['settings']['manualCapture']['isEnabled'] ?? false;
            error_log(print_r($merchantPreferences['settings']['manualCapture'],true),3,BP."/var/log/Order_place_settings.log");
            if (empty($manualCapture) || $manualCapture === false) {
                $this->createInvoice($orderId);
            }
            
            $this->_checkoutSession->clearQuote()->clearStorage();
            $this->_checkoutSession->clearQuote();
            $this->_checkoutSession->clearStorage();
            $this->_checkoutSession->clearHelperData();
            $this->_checkoutSession->resetCheckout();
            $this->_checkoutSession->restoreQuote();
        }
    }

    protected function createInvoice($orderId)
    {
        try {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/custom.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info('createInvoice');
            $order = $this->_orderRepository->get($orderId);

            $logger->info('payment method : ' . $order->getPayment()->getMethod());
            if ($order && $order->getPayment()->getMethod() == 'nofraud') {
                $logger->info('start automatic create invoice : ');
                $invoices = $this->_invoiceCollectionFactory->create()
                    ->addAttributeToFilter('order_id', array('eq' => $order->getId()));

                $invoices->getSelect()->limit(1);

                if ((int)$invoices->count() !== 0) {
                    $invoices = $invoices->getFirstItem();
                    $invoice = $this->_invoiceRepository->get($invoices->getId());
                    return $invoice;
                }

                if (!$order->canInvoice()) {
                    return null;
                }

                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(false);
                $invoice->getOrder()->setIsInProcess(true);
                $order->addStatusHistoryComment(__('Automatically INVOICED for No Fraud'), false);
                $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();

                $logger->info('curent status : ' . $order->getStatus());

                return $invoice;
            }
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }

    private function getNofraudSettings() 
    {
        $nfToken    = $this->dataHelper->getNofrudCheckoutAppNfToken();
        $merchantId = $this->dataHelper->getMerchantId();
        $apiUrl     = $this->dataHelper->getNofraudMerSettings().$merchantId;
        error_log("\n order place time manual capture check ".$apiUrl,3,BP."/var/log/order_place_manul_capture.log");
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
            error_log("\n order place time manual capture check ".$e->getMessage(),3,BP."/var/log/order_place_manul_capture.log");
        }
    }
}

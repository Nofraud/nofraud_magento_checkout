<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\CustomerInformationInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Logger;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Customer\Model\Customer;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class CustomerInformation implements CustomerInformationInterface
{
    const DATE_FORMAT = 'm/d/Y';

    protected $request;

    protected $customerRepository;

    protected $customerLog;

    protected $orderCollectionFactory;

    protected $customerCollection;

    protected $paymenttokenmanagement;

    public function __construct(
        Request                         $request,
        CustomerRepositoryInterface     $customerRepository,
        Logger                          $customerLog,
        CollectionFactory               $orderCollectionFactory,
        Customer                        $customerCollection,
        PaymentTokenManagementInterface $paymenttokenmanagement
    ) {
        $this->request                = $request;
        $this->customerRepository     = $customerRepository;
        $this->customerLog            = $customerLog;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerCollection     = $customerCollection;
        $this->paymenttokenmanagement = $paymenttokenmanagement;
    }

    public function getCustomerInformation()
    {
        try {
            $body       = $this->request->getBodyParams();
            $customerId = $body['data']['customer_id'];

            // Retrieve customer information by ID
            $customer      = $this->customerRepository->getById($customerId);
            $id            = $customer->getId();
            $email         = $customer->getEmail();
            $createdAt     = $customer->getCreatedAt();
            $createdDate   = date(self::DATE_FORMAT, strtotime($createdAt));
            $lastLoginAt   = $this->customerLog->get($customerId);
            $lastLoginDate = date(self::DATE_FORMAT, strtotime($lastLoginAt->getLastLoginAt()));

            // Retrieve customer order collection
            $orderCollection = $this->orderCollectionFactory->create()
                ->addFieldToFilter('customer_id', $customerId)
                ->addAttributeToSort('entity_id', 'DESC');
            $remoteIp               = "";
            $lastOrderDateFormatted = "";
            $totalOrders            = "";
            $totalOrderAmount       = "";
            if (count($orderCollection)) {
                // Retrieve details from the last order
                $lastOrder     = $orderCollection->getFirstItem();
                $remoteIp      = $lastOrder->getRemoteIp();
                $lastOrderDate = $lastOrder->getCreatedAt();
                $lastOrderDateFormatted = date(self::DATE_FORMAT, strtotime($lastOrderDate));
                $totalOrders       = $orderCollection->getSize();
                $totalOrderAmounts = $orderCollection->getColumnValues('grand_total');
                $totalOrderAmount  = array_sum($totalOrderAmounts);
            }

            // Retrieve additional customer information
            $customersCollection = $this->customerCollection->getCollection()
                ->addAttributeToSelect("*")
                ->addAttributeToFilter("entity_id", $customerId)
                ->load();
            $customerInformationAll = $customersCollection->getData();
            $customerInformation = array();
            foreach ($customerInformationAll as $customerInfo) {
                unset($customerInfo['password_hash']);
                unset($customerInfo['rp_token']);
                unset($customerInfo['rp_token_created_at']);
                $customerInformation[] = $customerInfo;
            }

            // Retrieve customer's saved credit card date
            $cardList = $this->paymenttokenmanagement->getListByCustomerId($customerId);
            $customerLastUsedCardDate = "";
            if ($cardList) {
                // Retrieve the last used card's date
                foreach ($cardList as $card) {
                    $customerUsedCards = $card->getData();
                    end($customerUsedCards);
                    $customerLastUsedCardDate = date(self::DATE_FORMAT, strtotime($customerUsedCards['created_at']));
                }
            }

            $response = [
                [
                    "code" => 'success',
                    "message" => [
                        'customer_ip_address'             => $remoteIp,
                        'customer_id'                     => $id,
                        'customer_email'                  => $email,
                        'customer_created_date'           => $createdDate,
                        'customer_last_login_date'        => $lastLoginDate,
                        'customer_last_order_date'        => $lastOrderDateFormatted,
                        'customer_total_orders'           => $totalOrders,
                        'customer_total_order_amount'     => $totalOrderAmount,
                        'customer_saved_credit_card_date' => $customerLastUsedCardDate,
                        'customer_information'            => $customerInformation
                    ]
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
        return $response;
    }
}

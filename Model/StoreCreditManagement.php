<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\StoreCreditManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Module\Manager;
use Amasty\StoreCredit\Api\Data\SalesFieldInterface;
use Magento\Framework\ObjectManagerInterface;

class StoreCreditManagement implements StoreCreditManagementInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        Manager                 $moduleManager,
        CartRepositoryInterface $quoteRepository,
        Request                 $request,
        ObjectManagerInterface  $objectManager
    ) {
        $this->moduleManager   = $moduleManager;
        $this->quoteRepository = $quoteRepository;
        $this->request         = $request;
        $this->objectManager   =  $objectManager;
    }

    public function apply()
    {
        if ($this->moduleManager->isEnabled('Amasty_StoreCredit')) {
            $body    = $this->request->getBodyParams();
            $cartId  = $body['data']['cart_id'];
            $amount  = $body['data']['amount'];

            if (isset($cartId) && isset($amount) && floatval($amount)) {
                try {
                    $quote = $this->quoteRepository->get($cartId);

                    $customerId = $quote->getCustomer()->getId();
                    $storeCreditCollection = $this->objectManager->get("\Amasty\StoreCredit\Model\StoreCredit\ResourceModel\Collection");
                    $storeCredit = $storeCreditCollection->getByCustomerId($customerId);

                    if ($storeCredit && $storeCredit->getStoreCredit() > 0) {
                        $quote->setData(SalesFieldInterface::AMSC_USE, 1);
                        $quote->setData(SalesFieldInterface::AMSC_AMOUNT, abs($amount));
                        $quote->collectTotals();
                        $this->quoteRepository->save($quote);

                        $response = [
                            [
                                "code" => 'success',
                                "message" => "The applied store credit amount is " . $quote->getData(SalesFieldInterface::AMSC_AMOUNT)
                            ],
                        ];
                    } else {
                        $response = [
                            [
                                "code"    => 'error',
                                "message" => 'Store credit for this customer was not found.'
                            ],
                        ];
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
                        "message" => "Please provide the amount and the cart ID",
                    ],
                ];
            }
        } else {
            $response = [
                [
                    "code" => 'error',
                    "message" => "The store credit extension is not enabled.",
                ],
            ];
        }
        return $response;
    }
    public function cancel()
    {
        if ($this->moduleManager->isEnabled('Amasty_StoreCredit')) {
            $body    = $this->request->getBodyParams();
            $cartId  = $body['data']['cart_id'];

            if (isset($cartId)) {
                try {
                    $quote = $this->quoteRepository->get($cartId);
                    if ($quote->getData(SalesFieldInterface::AMSC_AMOUNT) > 0) {
                        $quote->setData(SalesFieldInterface::AMSC_USE, 0);
                        $quote->collectTotals();
                        $this->quoteRepository->save($quote);

                        $response = [
                            [
                                "code" => 'success',
                                "message" => "The store credit amount was removed from the cart."
                            ],
                        ];
                    } else {
                        $response = [
                            [
                                "code"    => 'error',
                                "message" => 'The store credit amount is not found in the cart.'
                            ],
                        ];
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
                        "message" => "Please provide the cart ID",
                    ],
                ];
            }
        } else {
            $response = [
                [
                    "code" => 'error',
                    "message" => "The store credit extension is not enabled.",
                ],
            ];
        }
        return $response;
    }
}

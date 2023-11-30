<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\FacebookPixelInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Store\Model\StoreManagerInterface;

class FacebookPixelCheckoutEvent implements FacebookPixelInterface
{
    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;


    public function __construct(
        Manager                         $moduleManager,
        ObjectManagerInterface          $objectManager,
        QuoteFactory                    $quoteFactory,
        Request                         $request,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        StoreManagerInterface           $storeManager
    ) {
        $this->moduleManager          = $moduleManager;
        $this->objectManager          = $objectManager;
        $this->quoteFactory           = $quoteFactory;
        $this->request                = $request;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->_storeManager          = $storeManager;
    }

    /**
     * Fire the checkout event for Facebook Pixel
     *
     * @return array
     */
    public function fireCheckoutEvent()
    {
        if ($this->moduleManager->isEnabled('Apptrian_FacebookPixel')) {
            try {
                // Get the request body parameters
                $body        = $this->request->getBodyParams();
                $bodyQuoteId = $body['data']['cart_id'];

                // Get the helper instance for Apptrian Facebook Pixel module
                $helper = $this->objectManager->get('\Apptrian\FacebookPixel\Helper\Data');

                // Check if the user is logged in
                if (isset($body['data']['is_loggedin']) && $body['data']['is_loggedin']) {
                    $quoteId = $bodyQuoteId;
                } else {
                    // Convert masked quote ID to actual quote ID for guest users
                    $quoteId = $this->maskedQuoteIdToQuoteId->execute($bodyQuoteId);
                }
                error_log("\n quoteId : " . $quoteId, 3, BP . '/var/log/facebook.log');

                // Get the data for the Facebook Pixel event
                $data = $this->getQuoteDataForServer($quoteId, $helper);
                error_log("\n Facebook Pixel Data" . print_r($data, true), 3, BP . '/var/log/facebookPixel.log');
                if (empty($data)) {
                    $response = [
                        [
                            "code" => 'error',
                            "message" => 'Facebook Pixel Error : Invalid quote data'
                        ],
                    ];
                } else {
                    // Send the event data to the Facebook server
                    $fbApiResponse = $helper->fireServerEvent($data);

                    $response = [
                        [
                            "code" => 'success',
                            "message" => json_decode($fbApiResponse, true)
                        ],
                    ];
                }
            } catch (\Exception $e) {
                // Handle any exceptions and return error response
                $response = [
                    [
                        "code" => 'error',
                        "message" => $e->getMessage(),
                    ],
                ];
            }
        } else {
            // If the Apptrian Facebook Pixel module is not enabled, return error response
            $response = [
                [
                    "code" => 'error',
                    "message" => 'Facebook Pixel is not enabled'
                ],
            ];
        }

        return $response;
    }

    /**
     * Get the data required for the Facebook Pixel event
     *
     * @param int $quoteId
     * @param \Apptrian\FacebookPixel\Helper\Data $helper
     * @return array
     */
    public function getQuoteDataForServer($quoteId, $helper)
    {
        // Check if the InitiateCheckout event is enabled in the Apptrian Facebook Pixel module
        $isEnabled = $helper->isEventEnabled('InitiateCheckout', true);

        $i = 0;
        $d = [];

        if ($isEnabled) {
            // Get the data for the event
            $data = $this->getOrderOrQuoteData($quoteId, $helper);

            if (null != $data) {
                if ($quoteId) {
                    // Event name for InitiateCheckout
                    $eventName = 'InitiateCheckout';

                    // Get the quote based on the quote ID
                    $quote = $this->quoteFactory->create()->load($quoteId);

                    if ($quote->getCustomer()->getId()) {
                        $customerId = $quote->getCustomer()->getId();
                    } else {
                        $customerId = 0;
                    }

                    // Get user data for the event
                    $userData = $helper->getUserDataForServer($customerId);

                    // Set the data for the event
                    $d['data'][0]['event_name'] = $eventName;
                    $d['data'][0]['event_time'] = time();
                    $d['data'][0]['event_id']   = $helper->generateEventId($eventName, $quoteId, 'quote');

                    // Optional
                    $d['data'][0]['event_source_url'] = $this->_storeManager->getStore()->getBaseUrl() . 'checkout/';  //$helper->getCurrentUrl();
                    $d['data'][0]['user_data']        = $userData;
                    $d['data'][0]['custom_data']      = $data;

                    // Optional
                    $d['data'][0]['opt_out'] = false;

                    $i = 1;
                }
            }
        }

        // Add PageView based on config
        $isPageViewEnabled = $helper->isEventEnabled('PageView', true);
        $isPageViewWithAll = $helper->isPageViewWithAll(true);

        if ($isPageViewEnabled && $isPageViewWithAll) {
            // Get data for the PageView event
            $pageViewEvent = $helper->getDataForServerPageViewEvent($customerId);

            $currentUrl = $this->_storeManager->getStore()->getBaseUrl() . 'checkout/';
            if (isset($pageViewEvent['data'][0]['event_source_url'])) {
                $pageViewEvent['data'][0]['event_source_url'] = $currentUrl;
            }
            if (isset($pageViewEvent['data'][0]['event_id'])) {
                $pageViewEvent['data'][0]['event_id'] = $helper->generateEventId('PageView', $currentUrl, 'cms');
            }

            if ($i) {
                $pageViewData  = $pageViewEvent['data'][0];
                $d['data'][$i] = $pageViewData;
            } else {
                $d = $pageViewEvent;
            }
        }
        return $d;
    }

    /**
     * Get the data for the Facebook Pixel event based on the quote or order
     *
     * @param int $quoteId
     * @param \Apptrian\FacebookPixel\Helper\Data $helper
     * @return array|null
     */
    public function getOrderOrQuoteData($quoteId, $helper)
    {
        // Load the quote based on the quote ID
        $obj = $this->quoteFactory->create()->load($quoteId);

        if (!$quoteId) {
            return null;
        }

        // Get all items and visible items from the quote
        $allItems = $obj->getAllItems();
        $allVisibleItems = $obj->getAllVisibleItems();
        $group        = 'quote';
        $items        = [];
        $itemId       = '';
        $parentItemId = '';
        $i            = 0;
        $contents     = [];
        $data         = [];
        $product      = null;
        $productId    = 0;
        $productType  = '';
        $parent       = null;
        $parentId     = 0;
        $storeId      = $helper->getStoreId();
        $numItems     = 0;
        $taxFlag      = $helper->getDisplayTaxFlag();

        // Custom Parameters
        $attributeValue = '';
        $map = $helper->getParameterToAttributeMap($group);

        // Prepare data for all visible items in the quote
        foreach ($allVisibleItems as $item) {
            $itemId = $item->getItemId();

            $items[$itemId]['item_id']        = $itemId;
            $items[$itemId]['parent_item_id'] = $item->getParentItemId();
            $items[$itemId]['product_id']     = $item->getProductId();
            $items[$itemId]['product_type']   = $item->getProductType();
            $items[$itemId]['sku']            = $helper->filter($item->getSku());
            $items[$itemId]['name']           = $helper->filter($item->getName());
            $items[$itemId]['store_id']       = $item->getStoreId();

            if ($taxFlag) {
                $items[$itemId]['price'] = $helper->formatPrice($item->getPriceInclTax());
            } else {
                $items[$itemId]['price'] = $helper->formatPrice($item->getPrice());
            }

            if ($group == 'quote') {
                $items[$itemId]['qty'] = round($item->getQty(), 0);
            } else {
                $items[$itemId]['qty'] = round($item->getQtyOrdered(), 0);
            }
        }

        // Prepare data for all items in the quote
        foreach ($allItems as $item) {
            $itemId       = $item->getItemId();
            $parentItemId = $item->getParentItemId();

            if ($parentItemId) {
                $items[$parentItemId]['children'][$itemId]['item_id']        = $itemId;
                $items[$parentItemId]['children'][$itemId]['parent_item_id'] = $parentItemId;
                $items[$parentItemId]['children'][$itemId]['product_id']     = $item->getProductId();
                $items[$parentItemId]['children'][$itemId]['product_type']   = $item->getProductType();
                $items[$parentItemId]['children'][$itemId]['sku']            = $helper->filter($item->getSku());
                $items[$parentItemId]['children'][$itemId]['name']           = $helper->filter($item->getName());
                $items[$parentItemId]['children'][$itemId]['store_id']       = $item->getStoreId();

                if ($taxFlag) {
                    $items[$parentItemId]['children'][$itemId]['price'] = $helper->formatPrice($item->getPriceInclTax());
                } else {
                    $items[$parentItemId]['children'][$itemId]['price'] = $helper->formatPrice($item->getPrice());
                }

                if ($group == 'quote') {
                    if ($items[$parentItemId]['product_type'] == 'configurable') {
                        $q = $items[$parentItemId]['qty'];
                        $items[$parentItemId]['children'][$itemId]['qty'] = $q;
                    } else {
                        $items[$parentItemId]['children'][$itemId]['qty'] = round($item->getQty(), 0);
                    }
                } else {
                    $items[$parentItemId]['children'][$itemId]['qty'] = round($item->getQtyOrdered(), 0);
                }
            }
        }

        // Prepare data for all items and their children in the quote
        foreach ($items as $item) {
            $productId   = $item['product_id'];
            $productType = $item['product_type'];
            $storeId     = $item['store_id'];

            // Event options
            // 1 = product/parent
            // 2 = children/child
            // 3 = children/child/product and parent
            $option = (int) $helper->getConfig(
                'apptrian_facebookpixel/' . $group . '/ident_' . $productType,
                $storeId
            );

            $product    = $helper->getProductById($productId, $storeId);
            $productSku = $helper->filter($product->getSku());

            if ($productType == 'bundle' || $productType == 'configurable') {
                if ($option == 1) {
                    // Option 1 means show parent SKU only
                    $qty = $item['qty'];

                    if ($helper->getIdent() == 'id') {
                        $contents[$i]['id'] = $productId;
                    } else {
                        $contents[$i]['id'] = $productSku;
                    }

                    $contents[$i]['quantity']   = $qty;
                    $contents[$i]['item_price'] = $item['price'];

                    // Custom Parameters
                    $contents[$i] = $helper->addCustomParameters($map, $product, $contents[$i]);

                    $numItems += $qty;

                    $i++;
                } else {
                    // Option 2. or 3. means show children SKUs

                    $children = $item['children'];

                    foreach ($children as $child) {
                        $childProductId = $child['product_id'];
                        $childProduct   = $helper->getProductById($childProductId, $storeId);

                        $qty = $child['qty'];

                        if ($productType == 'bundle') {
                            // Bundle products may have global qty higher than 1
                            $parentItemId = $child['parent_item_id'];
                            $globalQty    = $items[$parentItemId]['qty'];
                            $qty          = $qty * $globalQty;
                        }

                        if ($helper->getIdent() == 'id') {
                            $contents[$i]['id'] = $helper->filter($childProduct->getId());
                        } else {
                            $contents[$i]['id'] = $helper->filter($childProduct->getSku());
                        }

                        $contents[$i]['quantity']   = $qty;
                        $contents[$i]['item_price'] = $child['price'];

                        // For configurable, you must use the configurable price, as the child price is 0
                        if ($productType == 'configurable') {
                            $contents[$i]['item_price'] = $item['price'];
                        }

                        if ($option == 3) {
                            // Option 3 adds parent product SKU on children
                            $contents[$i]['item_group_id'] = $helper->getItemGroupIdIdentifier(null, $productId, $productSku);
                        }

                        // Custom Parameters
                        $contents[$i] = $helper->addCustomParameters($map, $childProduct, $contents[$i]);

                        $numItems += $qty;

                        $i++;
                    }
                }
            } else {
                // Grouped, simple, virtual, downloadable products

                $qty = $item['qty'];

                if ($helper->getIdent() == 'id') {
                    $contents[$i]['id'] = $productId;
                } else {
                    $contents[$i]['id'] = $productSku;
                }

                $contents[$i]['quantity']   = $qty;
                $contents[$i]['item_price'] = $item['price'];

                // Reset parent ID
                $parentId = 0;

                if ($productType == 'grouped') {
                    if ($option == 3) {
                        // Get parent grouped product ID
                        $parentId = $helper->getParentGroupedProductId($productId);
                    }
                } else {
                    if ($option == 2) {
                        // Get parent product ID
                        $parentId = $helper->getParentProductId($productId);
                    }
                }

                if ($parentId) {
                    $parent = $helper->getProductById($parentId, $storeId);
                    if ($parent) {
                        $contents[$i]['item_group_id'] = $helper->getItemGroupIdIdentifier($parent);
                    }
                }

                // Custom Parameters
                $contents[$i] = $helper->addCustomParameters($map, $product, $contents[$i]);

                $numItems += $qty;

                $i++;
            }
        }

        // Check if there are items and return null if there are none
        if (empty($contents)) {
            return null;
        }

        // Order ID
        $orderIdParam = (string) $helper->getConfig(
            'apptrian_facebookpixel/' . $group . '/order_id_param',
            $storeId
        );
        if ($orderIdParam) {
            $data[$orderIdParam] = (string) $obj->getId();
        }

        // Order increment ID
        $orderIncrementIdParam = (string) $helper->getConfig(
            'apptrian_facebookpixel/' . $group . '/order_increment_id_param',
            $storeId
        );
        if ($orderIncrementIdParam) {
            $data[$orderIncrementIdParam] = (string) $obj->getIncrementId();
        }

        // Quote ID
        $quoteIdParam = (string) $helper->getConfig(
            'apptrian_facebookpixel/' . $group . '/quote_id_param',
            $storeId
        );
        if ($quoteIdParam) {
            if ($group == 'quote') {
                $data[$quoteIdParam] = (string) $obj->getId();
            } else {
                $data[$quoteIdParam] = (string) $obj->getQuoteId();
            }
        }

        // Set the data for the event
        $data['contents']     = $contents;
        $data['content_type'] = 'product';
        $data['num_items']    = $numItems;
        $data['value']        = $helper->formatPrice($obj->getGrandTotal());

        if ($group == 'quote') {
            $data['currency'] = $obj->getQuoteCurrencyCode();
        } else {
            $data['currency'] = $obj->getOrderCurrencyCode();
        }
        return $data;
    }
}

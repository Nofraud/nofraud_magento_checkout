<?php

namespace NoFraud\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory as ProductRepository;
use Magento\Catalog\Helper\ImageFactory as ProductImageHelper;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Quote\Api\Data\CartItemExtensionFactory;

class ProductInterface implements ObserverInterface
{
    const XML_PATH_ENABLED = 'nofraud/general/enabled';

    protected $scopeConfig;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     *@var \Magento\Catalog\Helper\ImageFactory
     */
    protected $productImageHelper;

    /**
     *@var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     *@var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * @var CartItemExtensionFactory
     */
    protected $extensionFactory;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Helper\ImageFactory
     * @param \Magento\Store\Model\StoreManagerInterface
     * @param \Magento\Store\Model\App\Emulation
     * @param CartItemExtensionFactory $extensionFactory
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ProductRepository $productRepository,
        ProductImageHelper $productImageHelper,
        StoreManager $storeManager,
        AppEmulation $appEmulation,
        CartItemExtensionFactory $extensionFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_objectManager = $objectManager;
        $this->productRepository = $productRepository;
        $this->productImageHelper = $productImageHelper;
        $this->storeManager = $storeManager;
        $this->appEmulation = $appEmulation;
        $this->extensionFactory = $extensionFactory;
    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function execute(\Magento\Framework\Event\Observer $observer, string $imageType = NULL)
    {
        $isNofraudenabled =  (int) $this->getConfig(self::XML_PATH_ENABLED);

        if ($isNofraudenabled) {

            $quote = $observer->getQuote();

            /**
             * Code to add the items attribute to extension_attributes
             */
            foreach ($quote->getAllItems() as $quoteItem) {
                $product = $this->productRepository->create()->getById($quoteItem->getProductId());
                $itemExtAttr = $quoteItem->getExtensionAttributes();
                if ($itemExtAttr === null) {
                    $itemExtAttr = $this->extensionFactory->create();
                }

                $imageurl = $this->productImageHelper->create()->init($product, 'product_thumbnail_image')->setImageFile($product->getThumbnail())->getUrl();

                $itemExtAttr->setImageUrl($imageurl);
                $quoteItem->setExtensionAttributes($itemExtAttr);
            }
            return;
        }
    }

    /**
     * Helper function that provides full cache image url
     * @param \Magento\Catalog\Model\Product
     * @return string
     */
    protected function getImageUrl($product, string $imageType = NULL)
    {
        $storeId = $this->storeManager->getStore()->getId();

        $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $imageUrl = $this->productImageHelper->create()->init($product, $imageType)->getUrl();

        $this->appEmulation->stopEnvironmentEmulation();

        return $imageUrl;
    }
}

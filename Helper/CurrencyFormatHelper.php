<?php

namespace NoFraud\Checkout\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\CurrencyInterface;

class CurrencyFormatHelper extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CurrencyInterface
     */
    protected $localeCurrency;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        CurrencyInterface $localeCurrency
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->localeCurrency = $localeCurrency;
    }

    /**
     * Determines the currency symbol position for a given currency code.
     *
     * @param string $currencyCode The currency code.
     * @return string 'Standard' or 'Right' indicating the symbol position.
     */
    public function getCurrencySymbolPosition($currencyCode)
    {
        $currency = $this->localeCurrency->getCurrency($currencyCode);
        $formatted = $currency->toCurrency(0);
        if (strpos($formatted, $currency->getSymbol()) <= strpos($formatted, '0')) {
            return 'STANDARD';
        } else {
            return 'RIGHT';
        }
    }
}

<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\CurrencyInformationInterface;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use NoFraud\Checkout\Helper\CurrencyFormatHelper;
use Psr\Log\LoggerInterface;

class CurrencyInformation implements CurrencyInformationInterface
{
    const REDIXPRICEDATA = 1000;
    const STANDARD = 8;
    const RIGHT = 16;
    const LEFT = 32;
    protected $logger;

    /**
     *
     * @var CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * @var Currency
     */
    protected $currency;

    protected $priceHelper;

    public function __construct(
        CurrencyFactory $currencyFactory,
        Currency $currency,
        PriceHelper $priceHelper,
        CurrencyFormatHelper $currencyFormatHelper,
        LoggerInterface $logger
    ) {
        $this->currencyFactory = $currencyFactory;
        $this->currency = $currency;
        $this->priceHelper = $priceHelper;
        $this->currencyFormatHelper = $currencyFormatHelper;
    }

    public function getCurrencyInformation($currencyCode)
    {
        try {
            $currency = $this->currencyFactory->create()->load($currencyCode);

            $symbolPosition = $this->currencyFormatHelper->getCurrencySymbolPosition($currencyCode);

            $priceFormeter = $this->priceHelper->currency(self::REDIXPRICEDATA);
            $radix = strip_tags($priceFormeter);
            $radixseparator = substr($radix, 6, 1);
            $thousandSeparator = substr($radix, 2, 1);
            $response = [
                [
                    "code" => 'success',
                    'symbol' => $currency->getCurrencySymbol(),
                    'symbolPosition' => $symbolPosition,
                    'radixSeparator' => $radixseparator,
                    'thousandSeparator' => $thousandSeparator
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

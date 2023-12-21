<?php

namespace NoFraud\Checkout\Model\Checks;

use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Payment\Model\Checks\ZeroTotal as OriginalZeroTotal;

class ZeroTotal extends OriginalZeroTotal
{

    public function isApplicable(MethodInterface $paymentMethod, Quote $quote)
    {
        return !($quote->getBaseGrandTotal() < 0.0001 && ($paymentMethod->getCode() != 'free' && $paymentMethod->getCode() != 'nofraud'));
    }
}

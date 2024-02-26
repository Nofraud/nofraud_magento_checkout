<?php

namespace NoFraud\Checkout\Api;

interface FacebookPixelInterface
{
    /**
     * @param  string $quoteId
     * @return array
     */
    public function fireCheckoutEvent();
}

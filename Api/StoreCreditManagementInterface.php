<?php

namespace NoFraud\Checkout\Api;

interface StoreCreditManagementInterface
{
    /**
     * Apply store credit amount to the cart.
     * @param string|int $cartId
     * @param string $amount
     * @return array
     */
    public function apply();

     /**
     * Cancel store credit amount from the cart.
     * @param string|int $cartId
     * @param string $amount
     * @return array
     */
    public function cancel();
}

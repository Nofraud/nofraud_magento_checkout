<?php

namespace NoFraud\Checkout\Api;

interface CustomerInformationInterface
{
    /**
     * @param int $customerId
     * @return array
     */
    public function getCustomerInformation();
}

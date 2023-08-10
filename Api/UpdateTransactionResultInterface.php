<?php

namespace NoFraud\Checkout\Api;

interface UpdateTransactionResultInterface
{
    /**
     * return status messages
     * @api
     * @param mixed $data
     * @return array
     */
    public function updateTransactionResult($data);
}
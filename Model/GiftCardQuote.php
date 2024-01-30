<?php

namespace NoFraud\Checkout\Model;

use NoFraud\Checkout\Api\Data\GiftCardQuoteInterface;

/**
 * Class Gift Card Quote
 * @package NoFraud\Checkout\Model
 */
class GiftCardQuote implements GiftCardQuoteInterface
{
    /**
     * @var int
     */
    private $quoteId;

    /**
     * @var array
     */
    private $giftCards;

    /**
     * @var float
     */
    private $giftAmount;

    /**
     * @var float
     */
    private $baseGiftAmount;

    /**
     * @var float
     */
    private $giftAmountUsed;

    /**
     * @var float
     */
    private $baseGiftAmountUsed;

    /**
     * @inheritdoc
     */
    public function getQuoteId()
    {
        return $this->quoteId;
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId($quoteId)
    {
        $this->quoteId = $quoteId;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getGiftCards()
    {
        return $this->giftCards;
    }

    /**
     * @inheritdoc
     */
    public function setGiftCards($giftCards)
    {
        $this->giftCards = $giftCards;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getGiftAmount()
    {
        return $this->giftAmount;
    }

    /**
     * @inheritdoc
     */
    public function setGiftAmount($giftAmount)
    {
        $this->giftAmount = $giftAmount;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBaseGiftAmount()
    {
        return $this->baseGiftAmount;
    }

    /**
     * @inheritdoc
     */
    public function setBaseGiftAmount($baseGiftAmount)
    {
        $this->baseGiftAmount = $baseGiftAmount;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getGiftAmountUsed()
    {
        return $this->giftAmountUsed;
    }

    /**
     * @inheritdoc
     */
    public function setGiftAmountUsed($giftAmountUsed)
    {
        $this->giftAmountUsed = $giftAmountUsed;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBaseGiftAmountUsed()
    {
        return $this->baseGiftAmountUsed;
    }

    /**
     * @inheritdoc
     */
    public function setBaseGiftAmountUsed($baseGiftAmountUsed)
    {
        $this->baseGiftAmountUsed = $baseGiftAmountUsed;
        return $this;
    }
}

<?php

namespace NoFraud\Checkout\Api\Data;

/**
 * Gift Card extension attribute for quote
 */
interface GiftCardQuoteInterface
{

    /**
     * Get quote Id
     *
     * @return int
     * @since 101.0.0
     */
    public function getQuoteId();

    /**
     * Set quote Id
     *
     * @param int $quoteId
     * @return $this
     * @since 101.0.0
     */
    public function setQuoteId($quoteId);

    /**
     * Get gift_cards
     *
     * @return \NoFraud\Checkout\Api\Data\GiftCardInterface[]
     * @since 101.0.0
     */
    public function getGiftCards();

    /**
     * Set gift_cards
     *
     * @param \NoFraud\Checkout\Api\Data\GiftCardInterface[] $giftCards
     * @return \NoFraud\Checkout\Api\Data\GiftCardQuoteInterface
     * @since 101.0.0
     */
    public function setGiftCards($giftCards);

    /**
     * Get gift_amount
     *
     * @return float
     * @since 101.0.0
     */
    public function getGiftAmount();

    /**
     * Set gift_amount
     *
     * @param float $giftAmount
     * @return $this
     * @since 101.0.0
     */
    public function setGiftAmount($giftAmount);

    /**
     * Get base_gift_amount
     *
     * @return float
     * @since 101.0.0
     */
    public function getBaseGiftAmount();

    /**
     * Set base_gift_amount
     *
     * @param float $baseGiftAmount
     * @return $this
     * @since 101.0.0
     */
    public function setBaseGiftAmount($baseGiftAmount);

    /**
     * Get gift_amount_used
     *
     * @return float
     * @since 101.0.0
     */
    public function getGiftAmountUsed();

    /**
     * Set gift_amount_used
     *
     * @param float $giftAmountUsed
     * @return $this
     * @since 101.0.0
     */
    public function setGiftAmountUsed($giftAmountUsed);

    /**
     * Get base_gift_amount_used
     *
     * @return float
     * @since 101.0.0
     */
    public function getBaseGiftAmountUsed();

    /**
     * Set base_gift_amount_used
     *
     * @param float $baseGiftAmountUsed
     * @return $this
     * @since 101.0.0
     */
    public function setBaseGiftAmountUsed($baseGiftAmountUsed);
}

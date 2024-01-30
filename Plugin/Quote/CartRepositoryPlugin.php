<?php

declare(strict_types=1);


namespace NoFraud\Checkout\Plugin\Quote;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\ObjectManager;
use NoFraud\Checkout\Model\GiftCardQuoteFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class CartRepositoryPlugin
{
    protected $extensionFactory;

    private   $giftCardQuoteFactory;

    protected $moduleManager;

    protected $serializer;

    protected $objectManager;

    public function __construct(
        CartExtensionFactory $extensionFactory,
        GiftCardQuoteFactory $giftCardQuoteFactory,
        Manager $moduleManager,
        ObjectManagerInterface $objectManager,
        Json $serializer = null
    ) {
        $this->extensionFactory     = $extensionFactory;
        $this->giftCardQuoteFactory = $giftCardQuoteFactory;
        $this->moduleManager        = $moduleManager;
        $this->objectManager        = $objectManager;
        $this->serializer           = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return CartInterface
     */
    public function afterGet(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->extensionFactory->create();
        }
        try {
            $giftCardQuote   = $this->giftCardQuoteFactory->create();

            if ($this->moduleManager->isEnabled('Magento_GiftCardAccount') && $this->moduleManager->isEnabled('Magento_GiftCard') && $quote->getGiftCards()) {
                $giftCards = $this->createGiftCards($this->serializer->unserialize($quote->getGiftCards()));
                if (!empty($giftCards)) {
                    $giftCardQuote->setQuoteId($quote->getId());
                    $giftCardQuote->setGiftCards($giftCards);
                    $giftCardQuote->setGiftAmount($quote->getGiftCardsAmount());
                    $giftCardQuote->setBaseGiftAmount($quote->getBaseGiftCardsAmount());
                    $giftCardQuote->setGiftAmountUsed($quote->getGiftCardsAmountUsed());
                    $giftCardQuote->setBaseGiftAmountUsed($quote->getBaseGiftCardsAmountUsed());
                    $extensionAttributes->setNfGiftcardQuote($giftCardQuote);
                    $quote->setExtensionAttributes($extensionAttributes);
                }
            } elseif ($this->moduleManager->isEnabled('Amasty_GiftCardAccount') && $this->moduleManager->isEnabled('Amasty_GiftCard')) {
                $AmGiftcardQuote = $quote->getExtensionAttributes()->getAmGiftcardQuote();
                if ($AmGiftcardQuote !== null) {
                    $quoteId = (int)$quote->getId();
                    $Repository = $this->objectManager->get("\Amasty\GiftCardAccount\Model\GiftCardExtension\Quote\Repository");
                    $amGiftCardQuote = $Repository->getByQuoteId($quoteId);
                    $giftCardQuote->setQuoteId($amGiftCardQuote->getQuoteId());
                    $giftCardQuote->setGiftCards($amGiftCardQuote->getGiftCards());
                    $giftCardQuote->setGiftAmount($amGiftCardQuote->getGiftAmount());
                    $giftCardQuote->setBaseGiftAmount($amGiftCardQuote->getBaseGiftAmount());
                    $giftCardQuote->setGiftAmountUsed($amGiftCardQuote->getGiftAmountUsed());
                    $giftCardQuote->setBaseGiftAmountUsed($amGiftCardQuote->getBaseGiftAmountUsed());
                    $extensionAttributes->setNfGiftcardQuote($giftCardQuote);
                    $quote->setExtensionAttributes($extensionAttributes);
                }
            }
        } catch (NoSuchEntityException $e) {
            error_log("\nException: " . $e->getMessage(), 3, BP . "/var/log/ExceptionGiftCard.log");
        }
        return $quote;
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param SearchResultsInterface $searchResult
     *
     * @return SearchResultsInterface
     */
    public function afterGetList(
        CartRepositoryInterface $subject,
        SearchResultsInterface $searchResult
    ): SearchResultsInterface {

        foreach ($searchResult->getItems() as $quote) {
            $this->afterGet($subject, $quote);
        }
        return $searchResult;
    }

    /**
     * Create Gift Cards Data Objects
     *
     * @param array $items
     * @return array
     */
    private function createGiftCards(array $items)
    {
        $giftCards = [];
        foreach ($items as $item) {
            if (isset($item['applied'])) {
                $giftCards[] = [
                    'id'       => $item['i'],
                    'code'     => $item['c'],
                    'amount'   => $item['a'],
                    'b_amount' => $item['ba'],
                    'applied'  => $item['applied']
                ];
            } else {
                $giftCards[] = [
                    'id'       => $item['i'],
                    'code'     => $item['c'],
                    'amount'   => $item['a'],
                    'b_amount' => $item['ba']
                ];
            }
        }
        return $giftCards;
    }
}

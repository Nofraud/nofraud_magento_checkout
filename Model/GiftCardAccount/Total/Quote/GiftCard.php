<?php

namespace NoFraud\Checkout\Model\GiftCardAccount\Total\Quote;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;

class GiftCard extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    protected $moduleManager;

    public function __construct(
        Manager $moduleManager,
        Json $serializer = null
    ) {
        $this->moduleManager = $moduleManager;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        try {
            if ($this->moduleManager->isEnabled('Magento_GiftCardAccount') && $this->moduleManager->isEnabled('Magento_GiftCard') && $quote->getGiftCards()) {
                $amGiftCards     = $this->createGiftCards($this->serializer->unserialize($total->getGiftCards()));
                $giftCards       = $this->serializer->unserialize($quote->getGiftCards());
                if ($amGiftCards) {
                    foreach ($giftCards as &$giftCard) {
                        foreach ($amGiftCards as $amGiftCard) {
                            if ($amGiftCard['id'] == $giftCard['i']) {
                                $usedAmount = $amGiftCard['amount'];
                                $giftCard['applied'] = $usedAmount;
                            }
                        }
                    }
                    $quote->setGiftCards($this->serializer->serialize($giftCards));
                    $quote->save();
                }
            } elseif ($this->moduleManager->isEnabled('Amasty_GiftCardAccount') && $this->moduleManager->isEnabled('Amasty_GiftCard')) {
                $amGiftcardQuote = $quote->getExtensionAttributes()->getAmGiftcardQuote();
                if ($amGiftcardQuote) {
                    $giftCards       = $amGiftcardQuote->getGiftCards();
                    $amGiftCards     = $total->getAmGiftCards();

                    if ($amGiftCards) {
                        foreach ($giftCards as &$giftCard) {
                            foreach ($amGiftCards as $amGiftCard) {
                                if ($amGiftCard['id'] == $giftCard['id']) {
                                    $usedAmount = $amGiftCard['amount'];
                                    $giftCard['applied'] = $usedAmount;
                                }
                            }
                        }
                        $amGiftcardQuote->setGiftCards($giftCards);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("\nException: " . $e->getMessage(), 3, BP . "/var/log/Exception_NF_gift_card_quote.log");
        }

        return $this;
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
            $giftCards[] = [
                'id'       => $item['i'],
                'code'     => $item['c'],
                'amount'   => $item['a'],
                'b_amount' => $item['ba'],
            ];
        }
        return $giftCards;
    }
}

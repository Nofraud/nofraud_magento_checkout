<?php
namespace NoFraud\Checkout\Model;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class ShippingInformationManagement extends \Magento\Checkout\Model\ShippingInformationManagement
{

    public function saveAddressInformation(
        $cartId,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        /** @var Quote $quote */

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/customoverride.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);


        $quote = $this->quoteRepository->getActive($cartId);
        $this->validateQuote($quote);

        $address = $addressInformation->getShippingAddress();
        $this->validateAddress($address);
        $this->addRegionIdToAddress($address);


        // custom code
        $currentCurrency = $quote->getQuoteCurrencyCode();
        $logger->info('currency : ' . $currentCurrency);

        if (!$address->getCustomerAddressId()) {
            $address->setCustomerAddressId(null);
        }

        try {
            $billingAddress = $addressInformation->getBillingAddress();
            if ($billingAddress) {
                if (!$billingAddress->getCustomerAddressId()) {
                    $billingAddress->setCustomerAddressId(null);
                }
                $this->addressValidator->validateForCart($quote, $billingAddress);
                $quote->setBillingAddress($billingAddress);
            }

            $this->addressValidator->validateForCart($quote, $address);
            $carrierCode = $addressInformation->getShippingCarrierCode();
            $address->setLimitCarrier($carrierCode);
            $methodCode = $addressInformation->getShippingMethodCode();
            $quote = $this->prepareShippingAssignment($quote, $address, $carrierCode . '_' . $methodCode);

            $quote->setIsMultiShipping(false);

            $logger->info('set new ');

            $logger->info('before save : ' . $quote->getQuoteCurrencyCode());
            $this->quoteRepository->save($quote);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $_quote = $objectManager->create('Magento\Quote\Model\Quote')->load($quote->getId());
            $_quote->setQuoteCurrencyCode($currentCurrency)->save();

        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            throw new InputException(
                __(
                    'The shipping information was unable to be saved. Error: "%message"',
                    ['message' => $e->getMessage()]
                )
            );
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new InputException(
                __('The shipping information was unable to be saved. Verify the input data and try again.')
            );
        }

        $shippingAddress = $quote->getShippingAddress();

        if (
            !$quote->getIsVirtual()
            && !$shippingAddress->getShippingRateByCode($shippingAddress->getShippingMethod())
        ) {
            $errorMessage = $methodCode ?
                __('Carrier with such method not found: %1, %2', $carrierCode, $methodCode)
                : __('The shipping method is missing. Select the shipping method and try again.');
            throw new NoSuchEntityException(
                $errorMessage
            );
        }

        /** @var PaymentDetailsInterface $paymentDetails */
        $paymentDetails = $this->paymentDetailsFactory->create();
        $paymentDetails->setPaymentMethods($this->paymentMethodManagement->getList($cartId));
        $paymentDetails->setTotals($this->cartTotalsRepository->get($cartId));

        return $paymentDetails;
    }

    private function validateAddress(?AddressInterface $address): void
    {
        if (!$address || !$address->getCountryId()) {
            throw new StateException(__('The shipping address is missing. Set the address and try again.'));
        }
    }

    private function prepareShippingAssignment(CartInterface $quote, AddressInterface $address, $method): CartInterface
    {
        $cartExtension = $quote->getExtensionAttributes();
        if ($cartExtension === null) {
            $cartExtension = $this->cartExtensionFactory->create();
        }

        $shippingAssignments = $cartExtension->getShippingAssignments();
        if (empty($shippingAssignments)) {
            $shippingAssignment = $this->shippingAssignmentFactory->create();
        } else {
            $shippingAssignment = $shippingAssignments[0];
        }

        $shipping = $shippingAssignment->getShipping();
        if ($shipping === null) {
            $shipping = $this->shippingFactory->create();
        }

        $shipping->setAddress($address);
        $shipping->setMethod($method);
        $shippingAssignment->setShipping($shipping);
        $cartExtension->setShippingAssignments([$shippingAssignment]);
        return $quote->setExtensionAttributes($cartExtension);
    }

    /**
     * Add region id to address if it is not set
     *
     * @param AddressInterface $address
     * @return void
     */
    private function addRegionIdToAddress(AddressInterface $address): void
    {
        // If region is already set, no need to set it again
        if ($address->getRegionId()) {
            return;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $region = $objectManager->create('Magento\Directory\Model\Region');

        $addressRegion = $address->getRegion();
        $addressRegionCode = $addressRegion ? $addressRegion->getRegionCode() : null;

        if ($addressRegionCode) {
            // No need to check if the country id is set, as it is validated in the validateAddress method
            $regionLoadResult = $region->loadByCode($addressRegionCode, $address->getCountryId());
            $regionId = $regionLoadResult ? $regionLoadResult->getId() : null;

            if ($regionId) {
                $address->setRegionId($regionId);
            }
        }
    }
}

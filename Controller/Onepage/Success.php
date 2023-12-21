<?php

namespace NoFraud\Checkout\Controller\Onepage;

use Magento\Checkout\Controller\Onepage\Success as OriginalSuccess;

class Success extends OriginalSuccess
{
    public function execute()
    {
        try {
            $orderId = $this->getRequest()->getParam('orderId');
            $session = $this->getOnepage()->getCheckout();
            $resultPage = $this->resultPageFactory->create();
            if ($orderId) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order = $objectManager->get(\Magento\Sales\Api\Data\OrderInterface::class)->loadByIncrementId($orderId);
                $session->setLastOrderId($order->getId());
                $session->setLastRealOrderId($orderId);
            } else {
                if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
                $session->clearQuote();
                $this->_eventManager->dispatch(
                    'checkout_onepage_controller_success_action',
                    [
                        'order_ids' => [$session->getLastOrderId()],
                        'order' => $session->getLastRealOrder()
                    ]
                );
            }
        } catch (\Exception $e) {
            error_log("\n " . $e->getMessage(), 3, BP . "/var/log/CheckoutSuccessLog.log");
        }
        return $resultPage;
    }
}

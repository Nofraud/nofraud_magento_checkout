<?php

namespace NoFraud\Checkout\Controller\Onepage;

use Magento\Checkout\Controller\Onepage\Success as OriginalSuccess;

class Success extends OriginalSuccess
{
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('orderId');
        $session = $this->getOnepage()->getCheckout();
        $resultPage = $this->resultPageFactory->create();
        if ($orderId) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $order = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId);
            $session->setLastOrderId($order->getIncrementId());
            $session->setLastRealOrderId($order->getIncrementId());
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
        return $resultPage;
    }
}

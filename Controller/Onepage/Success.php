<?php
namespace NoFraud\Checkout\Controller\Onepage;
use Magento\Checkout\Controller\Onepage\Success as OriginalSuccess;

class Success extends OriginalSuccess
{
    protected $order;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterface $order
    ) {
        $this->order = $order;
    }

    public function execute()
    {
        $orderId = $this->getRequest()->getParam('orderId');
        $session = $this->getOnepage()->getCheckout();
        if ($orderId) {
            $order = $this->order->load($orderId);
            $session->setLastOrderId($order->getIncrementId());
            $session->setLastRealOrder($order);
            $session->setLastQuoteId($order->getQuoteId());
            $session->setLastSuccessQuoteId($order->getQuoteId());
        }

        if (!$this->_objectManager->get(\Magento\Checkout\Model\Session\SuccessValidator::class)->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $session->clearQuote();

        $resultPage = $this->resultPageFactory->create();
        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$session->getLastOrderId()],
                'order' => $session->getLastRealOrder()
            ]
        );
        return $resultPage;
    }
}

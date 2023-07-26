<?php

namespace NoFraud\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class CheckoutSuccessFbPixelEvent implements ObserverInterface
{

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        Manager                 $moduleManager,
        ObjectManagerInterface  $objectManager
    ) {
        $this->moduleManager  = $moduleManager;
        $this->objectManager  = $objectManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        if ($this->moduleManager->isEnabled('Apptrian_FacebookPixel')) {

            $helper = $this->objectManager->get('\Apptrian\FacebookPixel\Helper\Data');

            $data = $helper->getOrderDataForServer();

            if (empty($data)) {
                return $this;
            }
            $helper->fireServerEvent($data);
        }
        return $this;
    }
}

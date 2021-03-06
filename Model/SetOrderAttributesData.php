<?php
namespace NoFraud\Checkout\Model;
use NoFraud\Checkout\Api\SetOrderAttributes;
class SetOrderAttributesData implements SetOrderAttributes
{
    protected $appEmulation;
    protected $storeManager;
    protected $logger;
	
    public function __construct(
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\Data\OrderInterface $order
    ) {
        $this->appEmulation = $appEmulation;
        $this->storeManager = $storeManager;
        $this->order 		= $order;
    }
	
	
    public function updateOrderAttributes($data)
    {
		
		$writer = new \Zend_Log_Writer_Stream(BP . '/var/log/customapi.log');
		$logger = new \Zend_Log();
		$logger->addWriter($writer);
		$logger->info('updateOrderAttributes');
		$logger->info(json_encode($data));
		
        $storeId 	= $this->storeManager->getStore()->getId();
		$order 		= $this->order->load($data["order_id"]);
        $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
		try{
            $logger->info(" order state ".$order->getState());
			$order->setData('nofraudcheckout',json_encode($data));
            $order->setStatus($order->getState());
            $order->setData('status',$order->getState());
			$order->save();
			$response = [
                [
                    "code" => 'success',
                    "message" => 'update data for order '. $order->getId() .' successful !',
                ],
            ];
        }catch(\Exception $e) {
			$response = [
                [
                    "code" => 'error',
                    "message" => $e->getMessage(),
                ],
            ];
        }
        $this->appEmulation->stopEnvironmentEmulation();
		
		return $response;
    }
}
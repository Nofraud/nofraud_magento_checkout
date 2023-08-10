<?php

namespace NoFraud\Checkout\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class UpdateOrders extends Command
{

    protected $_orderCollectionFactory;

    public function __construct(
        CollectionFactory $orderCollectionFactory
    ) {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('nofraud:update_orders_transaction_id');
        $this->setDescription('Update nofraudcheckout orders transaction id');

        parent::configure();
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $orderCollection = $this->_orderCollectionFactory->create()
                ->addFieldToFilter('nofraudcheckout', ['notnull' => true]);

            foreach ($orderCollection as $order) {
                $orderId             = $order->getId();
                $nofraudcheckoutData = json_decode($order->getNofraudcheckout(), true);
                $transactionId       = $nofraudcheckoutData['transaction_id'];
                $nfTransactionId     = $order->getNofraudTransactionId();
                $nfOrderId           = $nofraudcheckoutData['order_id'];

                if ($orderId == $nfOrderId && empty($nfTransactionId)) {
                    $order->setData('nofraud_transaction_id', $transactionId);
                    $order->save();
                    $output->writeln("Updated Order ID: " . $orderId);
                }
            }
            $output->writeln('<info>Orders updated successfully.</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}

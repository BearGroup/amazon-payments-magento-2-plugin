<?php

namespace Amazon\Pay\Command\Async;

use Amazon\Pay\Api\Data\AsyncInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Framework\App\ResourceConnection;

class ProcessOrderCommand extends Command
{
    /**
     * @var \Amazon\Pay\Model\ResourceModel\Async\CollectionFactory
     */
    private $asyncCollectionFactory;

    /**
     * @var \Amazon\Pay\Model\AsyncUpdater
     */
    private $asyncUpdater;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param \Amazon\Pay\Model\ResourceModel\Async\CollectionFactory $asyncCollectionFactory
     * @param \Amazon\Pay\Model\AsyncUpdater $asyncUpdater
     * @param \Magento\Framework\App\State $state
     * @param ResourceConnection $resource
     * @param string|null $name
     */
    public function __construct(
        \Amazon\Pay\Model\ResourceModel\Async\CollectionFactory $asyncCollectionFactory,
        \Amazon\Pay\Model\AsyncUpdater $asyncUpdater,
        \Magento\Framework\App\State $state,
        ResourceConnection $resource,
        string $name = null
    ) {
        $this->asyncCollectionFactory = $asyncCollectionFactory;
        $this->asyncUpdater = $asyncUpdater;
        $this->state = $state;
        $this->resource = $resource;

        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('amazon:payment:order:process')
            ->addArgument(
                'increment_id',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Order increment id(s) to process (separate multiple with spaces)'
            );
        parent::configure();
    }

    /**
     * Execute asynchronous processing of pending orders
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $incrementIds = (array) $input->getArgument('increment_id');

        $connection = $this->resource->getConnection();

        foreach ($incrementIds as $incrementId) {
            $output->writeln("<info>Processing order: {$incrementId}</info>");

            $txnSelect = $connection->select()
                ->from($connection->getTableName('sales_payment_transaction'), ['txn_id'])
                ->joinLeft(
                    ['so' => $connection->getTableName('sales_order')],
                    'so.entity_id = sales_payment_transaction.order_id',
                    []
                )
                ->where('so.increment_id = ?', $incrementId);

            $txnIds = $connection->fetchCol($txnSelect);

            if (empty($txnIds)) {
                $output->writeln("<comment>No payment transactions found for order {$incrementId}</comment>");
                continue;
            }

            foreach ($txnIds as $txnId) {
                $output->writeln("Found txn_id: {$txnId}");

                // 2. Get matching async records
                $collection = $this->asyncCollectionFactory->create();
                $collection->addFieldToFilter(AsyncInterface::PENDING_ID, $txnId);
                $collection->addFieldToFilter(AsyncInterface::IS_PENDING, ['eq' => 1]);

                if ($collection->getSize() === 0) {
                    $output->writeln("<comment>No async records found for txn_id {$txnId}</comment>");
                    continue;
                }

                foreach ($collection as $item) {
                    /** @var \Amazon\Pay\Model\Async $item */
                    $output->writeln("<info>Processing async entity #{$item->getId()} for txn_id {$txnId}</info>");
                    try {
                        $this->asyncUpdater->processPending($item);
                    } catch (\Exception $e) {
                        $output->writeln("<error>Error processing {$txnId}: {$e->getMessage()}</error>");
                    }
                }
            }
        }
        $output->writeln("<info>Finish order processing</info>");

        $code = defined('Command::SUCCESS') ? Command::SUCCESS : 0;
        return $code;
    }
}

<?php

namespace Amazon\Pay\Controller\Statistic;

use Amazon\Pay\Api\StatisticRepositoryInterface;
use Amazon\Pay\Model\StatisticRepository;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Index extends Action
{

    /**
     * @var StatisticRepositoryInterface
     */
    protected $repository;

    public function __construct(
        Context $context,
        StatisticRepositoryInterface $repository
    ) {
        parent::__construct($context);
        $this->repository = $repository;
    }

    public function execute()
    {
        $statisticData = $this->getRequest()->getParam('data');
        $this->repository->save($statisticData);

        $resultJson = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
        return $resultJson->setData(['success' => true]);
    }
}

<?php

namespace Amazon\Pay\Helper;

use Amazon\Pay\Api\Data\StatisticInterface;
use Amazon\Pay\Model\StatisticFactory;
use Amazon\Pay\Api\Data\StatisticErrorInterface;
use Amazon\Pay\Model\StatisticErrorFactory;
use Amazon\Pay\Api\StatisticRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;

class Statistic
{
    /**
     * @var StatisticRepositoryInterface
     */
    private $repository;

    private $errorRepository;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;


    /**
     * @param StatisticRepositoryInterface $repository
     */
    public function __construct(
        StatisticRepositoryInterface $repository,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->repository = $repository;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param array $statistic
     * @return void
     */
    public function save(array $statisticData)
    {
        $this->repository->save($statisticData);
    }

    /**
     * @param $statisticId
     * @return mixed
     */
    public function getById($statisticId)
    {
        return $this->repository->getById($statisticId);

    }

    /**
     * @param $customerId
     * @return array
     */
    public function getByCustomer($customerId)
    {
        $filter[] = $this->filterBuilder->setField('amazon_customer_id')
            ->setConditionType('eq')
            ->setValue($customerId);

        $searchCriteria = $this->searchCriteriaBuilder->addFilters($filter);
        $searchResults = $this->repository->getList($searchCriteria->create());
        $response = [];

        foreach ($searchResults->getItems() as $item) {

            $response[] = [
                'sku' => $item->getSku(),
                'price' => $item->getPrice(),
                'name' => $item->getName()
            ];
        }

        return $response;
    }
}

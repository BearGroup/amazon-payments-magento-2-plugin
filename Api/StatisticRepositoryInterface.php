<?php

namespace Amazon\Pay\Api;

use Amazon\Pay\Api\Data\StatisticInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface StatisticRepositoryInterface
{
    public function getById($statisticId);

    public function getList(SearchCriteriaInterface $searchCriteria);

    public function delete(StatisticInterface $statistic);

    public function deleteById($statisticId);

    public function save(array $statisticData);
}

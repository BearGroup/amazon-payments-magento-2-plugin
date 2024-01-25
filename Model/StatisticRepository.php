<?php

namespace Amazon\Pay\Model;

use Amazon\Pay\Api\Data\StatisticInterface;
use Amazon\Pay\Model\StatisticFactory;
use Amazon\Pay\Api\Data\StatisticSearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Amazon\Pay\Model\ResourceModel\Statistic as StatisticResourceModel;
use Amazon\Pay\Model\ResourceModel\Statistic\CollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;

class StatisticRepository implements \Amazon\Pay\Api\StatisticRepositoryInterface
{

    /**
     * @var StatisticResourceModel
     */
    private $resourceModel;

    /**
     * @var StatisticFactory
     */
    private $statisticFactory;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var StatisticSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @param StatisticResourceModel $resourceModel
     * @param \Amazon\Pay\Model\StatisticFactory $statisticFactory
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StatisticSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        StatisticResourceModel $resourceModel,
        StatisticFactory $statisticFactory,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StatisticSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resourceModel = $resourceModel;
        $this->statisticFactory = $statisticFactory;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    public function getById($entityId)
    {
        $statistic = $this->statisticFactory->create();
        $this->resourceModel->load($statistic, $entityId);

        return $statistic;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return mixed
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());

        return $searchResults;
    }

    /**
     * @param StatisticInterface $statistic
     * @return true
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(StatisticInterface $statistic)
    {
        try {
            $this->resourceModel->delete($statistic);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    /**
     * @param $entityId
     * @return true
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById($entityId)
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * @param array $statisticData
     * @return StatisticInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(array $statisticData)
    {
        try {
            $statisticModel = $this->statisticFactory->create();
            $statisticModel->setData($statisticData);

            $this->resourceModel->save($statisticModel);
        } catch (\Exception $exception) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(
                __('Could not save Amazon Statistic: %1', $exception->getMessage()),
                $exception
            );
        }
        return $statisticModel;
    }
}

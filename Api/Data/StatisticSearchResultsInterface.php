<?php

namespace Amazon\Pay\Api\Data;

interface StatisticSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Gets collection items.
     *
     * @return \Amazon\Pay\Api\Data\StatisticInterface[] Array of collection items.
     */
    public function getItems();

    /**
     * Sets collection items.
     *
     * @param \Amazon\Pay\Api\Data\StatisticInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}

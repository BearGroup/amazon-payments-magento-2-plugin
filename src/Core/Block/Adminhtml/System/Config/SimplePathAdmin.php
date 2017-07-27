<?php

namespace Amazon\Core\Block\Adminhtml\System\Config;

use Amazon\Core\Helper\CategoryExclusion;
use Amazon\Core\Helper\Data;
use Amazon\Core\Model\Config\SimplePath;
use Magento\Customer\Model\Url;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class SimplePathAdmin extends Template
{
    /**
     * @var \Amazon\Core\Model\Config\simplePath
     */
    protected $_model;


    function __construct(
        Context $context,
        SimplePath $simplePath
    )
    {
        parent::__construct($context);
        $this->simplePath = $simplePath;
    }

    /**
     * Return SimplePath settings
     */
    function getAmazonSpJson()
    {
        return json_encode($this->simplePath->getJsonAmazonSpConfig());
    }

    /**
     * Return region
     */
    function getRegion()
    {
        return $this->simplePath->getRegion();
    }

    /**
     * Return currency
     */
    function getCurrency()
    {
        return $this->simplePath->getCurrency();
    }
}

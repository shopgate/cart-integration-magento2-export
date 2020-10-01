<?php

namespace Shopgate\Export\Helper\Product\Stock;

use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class Factory
{
    /** @var Manager */
    private $moduleManager;

    /** @var ObjectManagerInterface */
    private $objectManager;

    /**
     * @param Manager $moduleManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(Manager $moduleManager, ObjectManagerInterface $objectManager)
    {
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @return Utility
     */
    public function getUtility()
    {
        return $this->moduleManager->isEnabled('Magento_InventorySalesApi')
            ? $this->objectManager->create(UtilityInventorySalesApi::class)
            : $this->objectManager->create(UtilityCommon::class);
    }
}

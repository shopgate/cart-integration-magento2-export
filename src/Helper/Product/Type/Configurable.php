<?php

/**
 * Copyright Shopgate Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Shopgate Inc, 804 Congress Ave, Austin, Texas 78701 <interfaces@shopgate.com>
 * @copyright Shopgate Inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

namespace Shopgate\Export\Helper\Product\Type;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\Spi\StockStateProviderInterface;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Helper\Product\Type\Configurable as OriginalConfigurable;
use Shopgate\Base\Helper\Product\Utility;
use Shopgate\Export\Api\ExportInterface;

class Configurable extends OriginalConfigurable
{
    /** @var CoreInterface */
    private $scopeConfig;
    
    /**
     * @param Option\Repository           $productOptionRepo
     * @param StockRegistryInterface      $stockRegistry
     * @param StockStateProviderInterface $stateProvider
     * @param ProductFactory              $productFactory
     * @param Utility                     $productUtility
     * @param CoreInterface               $scopeConfig
     */
    public function __construct(
        Option\Repository $productOptionRepo,
        StockRegistryInterface $stockRegistry,
        StockStateProviderInterface $stateProvider,
        ProductFactory $productFactory,
        Utility $productUtility,
        CoreInterface $scopeConfig
    ) {
        parent::__construct($productOptionRepo, $stockRegistry, $stateProvider, $productFactory, $productUtility);
        
        $this->scopeConfig = $scopeConfig;
    }
    
    /**
     * @inheritdoc
     */
    public function getChildren()
    {
        $childProductIds   = $this->getItem()->getTypeInstance()->getChildrenIds($this->getItem()->getId());
        $childProductIds   = current($childProductIds);
        $productCollection = $this->productFactory->create()->getCollection();
        $productCollection->addAttributeToFilter('entity_id', ['in' => $childProductIds]);
        $productCollection->addStoreFilter();
        $productCollection->addAttributeToSelect('*');
        $productCollection->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED]);
        
        if ($this->scopeConfig->getConfigByPath(ExportInterface::PATH_PROD_OUT_OF_STOCK)->getData('value')) {
            $productCollection->setFlag('has_stock_status_filter', false);
        }
        
        return $productCollection;
    }
}
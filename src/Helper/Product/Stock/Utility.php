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

namespace Shopgate\Export\Helper\Product\Stock;

use Magento\Catalog\Model\Product as MageProduct;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryExportStock\Model\GetStockItemConfiguration;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\Store\Model\StoreManager;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Model\Shopgate\Product\StockItem;
use Shopgate\Export\Model\Shopgate\Product\StockItemFactory as ShopgateStockItemFactory;

class Utility
{
    /** @var SgLoggerInterface */
    protected $log;

    /** @var ShopgateStockItemFactory */
    protected $shopgateStockItemFactory;

    /** @var ProductMetadataInterface */
    protected $productMetadata;

    /** @var StockItemResource */
    protected $stockItemResource;

    /** @var StockItemInterfaceFactory */
    protected $stockItemFactory;

    /** @var StoreManager */
    protected $storeManager;

    /** @var GetStockIdForCurrentWebsite */
    protected $getStockIdForCurrentWebsite;

    /** @var GetStockItemDataInterface */
    protected $getStockItemData;

    /** @var GetStockItemConfiguration */
    protected $getStockItemConfiguration;

    /** @var StockConfigurationInterface */
    protected $stockConfiguration;

    /** @var StockRegistryInterface */
    protected $stockRegistry;

    /**
     * @param SgLoggerInterface         $logger
     * @param ShopgateStockItemFactory  $productStockItemFactory
     * @param ProductMetadataInterface  $productMetadata
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockItemResource         $stockItemResource
     * @param StoreManager              $storeManager
     */
    public function __construct(
        SgLoggerInterface $logger,
        ShopgateStockItemFactory $productStockItemFactory,
        ProductMetadataInterface $productMetadata,
        StockItemInterfaceFactory $stockItemFactory,
        StockItemResource $stockItemResource,
        StoreManager $storeManager,
        StockConfigurationInterface $stockConfiguration,
        StockRegistryInterface $stockRegistry
    ) {
        $this->log                      = $logger;
        $this->shopgateStockItemFactory = $productStockItemFactory;
        $this->productMetadata          = $productMetadata;
        $this->stockItemResource        = $stockItemResource;
        $this->stockItemFactory         = $stockItemFactory;
        $this->storeManager             = $storeManager;
        $this->stockConfiguration       = $stockConfiguration;
        $this->stockRegistry            = $stockRegistry;

        if (version_compare($this->getCurrentVersion(), '2.3.0', '>=')) {
            $om                                = ObjectManager::getInstance();
            $this->getStockItemData            = $om->get(GetStockItemDataInterface::class);
            $this->getStockIdForCurrentWebsite = $om->get(GetStockIdForCurrentWebsite::class);
            $this->getStockItemConfiguration   = $om->get(GetStockItemConfiguration::class);
        }
    }

    /**
     * @param MageProduct $product
     *
     * @return StockItem
     * @throws LocalizedException
     */
    public function getStockItem($product): StockItem
    {
        /** @var StockItem $shopgateStockItem */
        $shopgateStockItem = $this->shopgateStockItemFactory->create();
        if (version_compare($this->getCurrentVersion(), '2.3.0', '>=')) {
            $stockId         = $this->getStockIdForCurrentWebsite->execute();
            $stockItemData   = $this->getStockItemData->execute($product->getSku(), $stockId);
            $stockItemConfig = $this->getStockItemConfiguration->execute($product->getSku());

            $shopgateStockItem->setStockQuantity((int) $stockItemData['quantity']);
            $shopgateStockItem->setIsSaleable((bool) $stockItemData['is_salable']);
            $shopgateStockItem->setUseStock($stockItemConfig->isManageStock());
            $shopgateStockItem->setBackorders((bool) $stockItemConfig->getBackorders());
            $shopgateStockItem->setMaximumOrderQuantity($stockItemConfig->getMaxSaleQty());
            $shopgateStockItem->setMinimumOrderQuantity($stockItemConfig->getMinSaleQty());

            return $shopgateStockItem;
        }

        $stockItem      = $this->stockItemFactory->create();
        $defaultScopeId = $this->stockConfiguration->getDefaultScopeId();
        $defaultStockId = $this->stockRegistry->getStock($defaultScopeId)->getStockId();
        $stockId        = $stockItem->getStockId();

        $this->stockItemResource->loadByProductId(
            $stockItem,
            $product->getId(),
            $stockId ?: $defaultStockId
        );

        $useStock = false;
        if ($stockItem->getManageStock()) {
            switch ($stockItem->getBackorders() && $stockItem->getIsInStock()) {
                case Stock::BACKORDERS_YES_NONOTIFY:
                case Stock::BACKORDERS_YES_NOTIFY:
                    break;
                default:
                    $useStock = true;
                    break;
            }
        }
        $shopgateStockItem->setUseStock($useStock);
        $shopgateStockItem->setBackorders((bool) $stockItem->getBackorders());
        $shopgateStockItem->setStockQuantity((int) $stockItem->getQty());
        $shopgateStockItem->setMaximumOrderQuantity($stockItem->getMaxSaleQty());
        $shopgateStockItem->setMinimumOrderQuantity($stockItem->getMinSaleQty());
        $shopgateStockItem->setIsSaleable(!$useStock ? : $product->getIsSalable());

        return $shopgateStockItem;
    }

    /**
     * @return string
     */
    protected function getCurrentVersion(): string
    {
        return $this->productMetadata->getVersion();
    }
}

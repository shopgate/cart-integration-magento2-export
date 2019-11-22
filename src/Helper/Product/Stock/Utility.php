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
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManager;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Model\Product\StockItemFactory as ProductStockItemFactory;

class Utility
{
    /** @var SgLoggerInterface */
    protected $log;

    /** @var ProductStockItemFactory */
    protected $stockItem;

    /** @var StockItem */
    protected $productMetadata;

    /** @var StockItemResource */
    protected $stockItemResource;

    /** @var StockItemInterfaceFactory */
    protected $stockItemFactory;

    /** @var StoreManager */
    protected $storeManager;

    /** @var var GetStockIdForCurrentWebsite */
    protected $getStockIdForCurrentWebsite;

    /** @var GetStockItemDataInterface */
    protected $getStockItemData;

    /** @var GetStockItemConfiguration */
    protected $getStockItemConfiguration;

    /**
     * @param SgLoggerInterface         $logger
     * @param ProductStockItemFactory   $productStockItemFactory
     * @param ProductMetadataInterface  $productMetadata
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockItemResource         $stockItemResource
     * @param StoreManager              $storeManager
     */
    public function __construct(
        SgLoggerInterface $logger,
        ProductStockItemFactory $productStockItemFactory,
        ProductMetadataInterface $productMetadata,
        StockItemInterfaceFactory $stockItemFactory,
        StockItemResource $stockItemResource,
        StoreManager $storeManager
    ) {
        $this->log               = $logger;
        $this->stockItem         = $productStockItemFactory->create();
        $this->productMetadata   = $productMetadata;
        $this->stockItemResource = $stockItemResource;
        $this->stockItemFactory  = $stockItemFactory;
        $this->storeManager      = $storeManager;

        $this->setDependencies();
    }

    /**
     * Set the dependencies based on version
     */
    protected function setDependencies()
    {
        if (version_compare($this->getCurrentVersion(), '2.3.0', '>=')) {
            $om                                = ObjectManager::getInstance();
            $this->getStockItemData            = $om->get('Magento\\InventorySalesApi\\Model\\GetStockItemDataInterface');
            $this->getStockIdForCurrentWebsite = $om->get('Magento\\InventoryCatalog\\Model\\GetStockIdForCurrentWebsite');
            $this->getStockItemConfiguration   = $om->get('Magento\\InventoryExportStock\\Model\\GetStockItemConfiguration');
        }
    }

    /**
     * @return string
     */
    protected function getCurrentVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * @param MageProduct $product
     *
     * @return \Shopgate\Export\Model\Product\StockItem|ProductStockItemFactory
     * @throws LocalizedException
     */
    public function getStockItem($product)
    {
        try {
            if (version_compare($this->getCurrentVersion(), '2.3.0', '>=')) {
                $stockId         = $this->getStockIdForCurrentWebsite->execute();
                $stockItemData   = $this->getStockItemData->execute($product->getSku(), $stockId);
                $stockItemConfig = $this->getStockItemConfiguration->execute($product->getSku(), $stockId);

                $this->stockItem->setStockQuantity((int)$stockItemData['quantity']);
                $this->stockItem->setIsSaleable((bool)$stockItemData['is_salable']);
                $this->stockItem->setUseStock((bool)$stockItemConfig->isManageStock());
                $this->stockItem->setBackorders((bool)$stockItemConfig->getBackorders());
                $this->stockItem->setMaximumOrderQuantity($stockItemConfig->getMaxSaleQty());
                $this->stockItem->setMinimumOrderQuantity($stockItemConfig->getMinSaleQty());

                return $this->stockItem;
            }

            $stockItem = $this->stockItemFactory->create();
            $this->stockItemResource->loadByProductId(
                $stockItem,
                $product->getId(),
                $this->storeManager->getWebsite()->getId()
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
            $this->stockItem->setUseStock((bool)$useStock);
            $this->stockItem->setBackorders((bool)$stockItem->getBackorders());
            $this->stockItem->setStockQuantity((int)$stockItem->getQty());
            $this->stockItem->setMaximumOrderQuantity($stockItem->getMaxSaleQty());
            $this->stockItem->setMinimumOrderQuantity($stockItem->getMinSaleQty());

            $useStock
                ? $this->stockItem->setIsSaleable($product->getIsSalable())
                : $this->stockItem->setIsSaleable(true);

            return $this->stockItem;
        } catch (LocalizedException $exception) {
            $this->log->error(
                "Can't handle stock of product with id: {$product->getId()}, message: " . $exception->getMessage()
            );
        }
    }
}

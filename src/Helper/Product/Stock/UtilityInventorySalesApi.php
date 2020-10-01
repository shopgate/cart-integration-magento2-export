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
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryExportStock\Model\GetStockItemConfiguration;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Model\Shopgate\Product\StockItem;
use Shopgate\Export\Model\Shopgate\Product\StockItemFactory as ShopgateStockItemFactory;

class UtilityInventorySalesApi implements Utility
{
    /** @var SgLoggerInterface */
    private $log;

    /** @var ShopgateStockItemFactory */
    private $shopgateStockItemFactory;

    /** @var GetStockItemDataInterface */
    private $getStockItemData;

    /** @var GetStockIdForCurrentWebsite */
    private $websiteStockId;

    /** @var GetStockItemConfiguration */
    private $stockItemConfig;

    /**
     * @param SgLoggerInterface $logger
     * @param ShopgateStockItemFactory $productStockItemFactory
     * @param GetStockItemDataInterface $getStockItemData
     * @param GetStockIdForCurrentWebsite $websiteStockId
     * @param GetStockItemConfiguration $stockItemConfig
     */
    public function __construct(
        SgLoggerInterface $logger,
        ShopgateStockItemFactory $productStockItemFactory,
        GetStockItemDataInterface $getStockItemData,
        GetStockIdForCurrentWebsite $websiteStockId,
        GetStockItemConfiguration $stockItemConfig
    )
    {
        $this->log = $logger;
        $this->shopgateStockItemFactory = $productStockItemFactory;
        $this->getStockItemData = $getStockItemData;
        $this->websiteStockId = $websiteStockId;
        $this->stockItemConfig = $stockItemConfig;
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

        $stockId = $this->websiteStockId->execute();
        $stockItemData = $this->getStockItemData->execute($product->getSku(), $stockId);
        $stockItemConfig = $this->stockItemConfig->execute($product->getSku());

        $shopgateStockItem->setStockQuantity((int)$stockItemData['quantity']);
        $shopgateStockItem->setIsSaleable((bool)$stockItemData['is_salable']);
        $shopgateStockItem->setUseStock($stockItemConfig->isManageStock());
        $shopgateStockItem->setBackorders((bool)$stockItemConfig->getBackorders());
        $shopgateStockItem->setMaximumOrderQuantity($stockItemConfig->getMaxSaleQty());
        $shopgateStockItem->setMinimumOrderQuantity($stockItemConfig->getMinSaleQty());

        return $shopgateStockItem;
    }
}

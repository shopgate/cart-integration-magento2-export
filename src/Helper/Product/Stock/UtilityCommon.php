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
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Model\Shopgate\Product\StockItem;
use Shopgate\Export\Model\Shopgate\Product\StockItemFactory as ShopgateStockItemFactory;

class UtilityCommon implements Utility
{
    /** @var SgLoggerInterface */
    protected $log;

    /** @var ShopgateStockItemFactory */
    protected $shopgateStockItemFactory;

    /** @var StockItemResource */
    protected $stockItemResource;

    /** @var StockItemInterfaceFactory */
    protected $stockItemFactory;

    /** @var StockConfigurationInterface */
    protected $stockConfiguration;

    /** @var StockRegistryInterface */
    protected $stockRegistry;

    /**
     * @param SgLoggerInterface $logger
     * @param ShopgateStockItemFactory $productStockItemFactory
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockItemResource $stockItemResource
     * @param StockConfigurationInterface $stockConfiguration
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(
        SgLoggerInterface $logger,
        ShopgateStockItemFactory $productStockItemFactory,
        StockItemInterfaceFactory $stockItemFactory,
        StockItemResource $stockItemResource,
        StockConfigurationInterface $stockConfiguration,
        StockRegistryInterface $stockRegistry
    ) {
        $this->log = $logger;
        $this->shopgateStockItemFactory = $productStockItemFactory;
        $this->stockItemResource = $stockItemResource;
        $this->stockItemFactory = $stockItemFactory;
        $this->stockConfiguration = $stockConfiguration;
        $this->stockRegistry = $stockRegistry;
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

        /** @var StockItemInterface $stockItem */
        $stockItem = $this->stockItemFactory->create();
        $defaultScopeId = $this->stockConfiguration->getDefaultScopeId();
        $defaultStockId = $this->stockRegistry->getStock($defaultScopeId)->getStockId();
        $stockId = $stockItem->getStockId();

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
        $shopgateStockItem->setBackorders((bool)$stockItem->getBackorders());
        $shopgateStockItem->setStockQuantity((int)$stockItem->getQty());
        $shopgateStockItem->setMaximumOrderQuantity($stockItem->getMaxSaleQty());
        $shopgateStockItem->setMinimumOrderQuantity($stockItem->getMinSaleQty());
        $shopgateStockItem->setIsSaleable(!$useStock ?: $product->getIsSalable());

        return $shopgateStockItem;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStockQuantityBySku(string $sku): array
    {
        $stock = $this->stockRegistry->getStockItemBySku($sku);

        return [['qty' => $stock->getQty()]];
    }
}

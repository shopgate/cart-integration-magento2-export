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

namespace Shopgate\Export\Model\Shopgate\Product;

use Magento\Framework\DataObject;

class StockItem extends DataObject
{
    public const MINIMUM_ORDER_QUANTITY = 'minimum_order_quantity';
    public const MAXIMUM_ORDER_QUANTITY = 'maximum_order_quantity';
    public const BACKORDERS             = 'backorders';
    public const USE_STOCK              = 'use_stock';
    public const IS_SALEABLE            = 'is_saleable';
    public const STOCK_QUANTITY         = 'stock_quantity';

    /**
     * @param int $stockQuantity
     */
    public function setStockQuantity(int $stockQuantity): void
    {
        $this->setData(self::STOCK_QUANTITY, $stockQuantity);
    }

    /**
     * @return int
     */
    public function getStockQuantity(): int
    {
        return $this->getData(self::STOCK_QUANTITY);
    }

    /**
     * @param bool $isSaleable
     */
    public function setIsSaleable(bool $isSaleable): void
    {
        $this->setData(self::IS_SALEABLE, $isSaleable);
    }

    /**
     * @return bool
     */
    public function getIsSaleable(): bool
    {
        return $this->getData(self::IS_SALEABLE);
    }

    /**
     * @param bool $useStock
     */
    public function setUseStock(bool $useStock): void
    {
        $this->setData(self::USE_STOCK, $useStock);
    }

    /**
     * @return bool
     */
    public function getUseStock(): bool
    {
        return $this->getData(self::USE_STOCK);
    }

    /**
     * @param bool $backorders
     */
    public function setBackorders(bool $backorders): void
    {
        $this->setData(self::BACKORDERS, $backorders);
    }

    /**
     * @return bool
     */
    public function getBackorders(): bool
    {
        return $this->getData(self::BACKORDERS);
    }

    /**
     * @param int $maximumOrderQuantity
     */
    public function setMaximumOrderQuantity(int $maximumOrderQuantity): void
    {
        $this->setData(self::MAXIMUM_ORDER_QUANTITY, $maximumOrderQuantity);
    }

    /**
     * @return int
     */
    public function getMaximumOrderQuantity(): int
    {
        return $this->getData(self::MAXIMUM_ORDER_QUANTITY);
    }

    /**
     * @param int $minimumOrderQuantity
     */
    public function setMinimumOrderQuantity(int $minimumOrderQuantity): void
    {
        $this->setData(self::MINIMUM_ORDER_QUANTITY, $minimumOrderQuantity);
    }

    /**
     * @return int
     */
    public function getMinimumOrderQuantity(): int
    {
        return $this->getData(self::MINIMUM_ORDER_QUANTITY);
    }
}

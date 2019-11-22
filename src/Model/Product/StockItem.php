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

namespace Shopgate\Export\Model\Product;

use Magento\Framework\DataObject;

class StockItem extends DataObject
{
    const MINIMUM_ORDER_QUANTITY = 'minimum_order_quantity';
    const MAXIMUM_ORDER_QUANTITY = 'maximum_order_quantity';
    const BACKORDERS             = 'backorders';
    const USE_STOCk              = 'use_stock';
    const IS_SALABLE             = 'is_salable';
    const STOCK_QUANTITY         = 'stock_quantity';

    /**
     * @param int $stockQuantity
     */
    public function setStockQuantity(int $stockQuantity)
    {
        $this->setData(self::STOCK_QUANTITY, $stockQuantity);
    }

    /**
     * @return int
     */
    public function getStockQuantity()
    {
        return $this->setData(self::STOCK_QUANTITY);
    }

    /**
     * @param bool $isSaleable
     */
    public function setIsSaleable(bool $isSaleable)
    {
        $this->setData(self::IS_SALABLE, $isSaleable);
    }

    /**
     * @return bool
     */
    public function getIsSaleable()
    {
        return $this->getData(self::IS_SALABLE);
    }

    /**
     * @param bool $useStock
     */
    public function setUseStock(bool $useStock)
    {
        $this->setData(self::USE_STOCk, $useStock);
    }

    /**
     * @return bool
     */
    public function getUseStock()
    {
        return $this->getData(self::USE_STOCk);
    }

    /**
     * @param bool $backorders
     */
    public function setBackorders(bool $backorders)
    {
        $this->setData(self::BACKORDERS, $backorders);
    }

    /**
     * @return bool
     */
    public function getBackorders()
    {
        return $this->getData(self::BACKORDERS);
    }

    /**
     * @param int $maximumOrderQuantity
     */
    public function setMaximumOrderQuantity(int $maximumOrderQuantity)
    {
        $this->setData(self::MAXIMUM_ORDER_QUANTITY, $maximumOrderQuantity);
    }

    /**
     * @return int
     */
    public function getMaximumOrderQuantity()
    {
        return $this->getData(self::MAXIMUM_ORDER_QUANTITY);
    }

    /**
     * @param int $minimumOrderQuantity
     */
    public function setMinimumOrderQuantity(int $minimumOrderQuantity)
    {
        $this->setData(self::MINIMUM_ORDER_QUANTITY, $minimumOrderQuantity);
    }

    /**
     * @return int
     */
    public function getMinimumOrderQuantity()
    {
        return $this->setData(self::MINIMUM_ORDER_QUANTITY);
    }
}

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

namespace Shopgate\Export\Model\Utility;

use Magento\Framework\DataObject;

class StockItem extends DataObject
{
    /**
     * @param int $stockQuantity
     */
    public function setStockQuantity($stockQuantity)
    {
        $this->setData('stock_quantity', $stockQuantity);
    }

    /**
     * @return int
     */
    public function getStockQuantity()
    {
        return $this->setData('stock_quantity');
    }

    /**
     * @param bool $isSaleable
     */
    public function setIsSaleable($isSaleable)
    {
        $this->setData('is_salable', $isSaleable);
    }

    /**
     * @return bool
     */
    public function getIsSaleable()
    {
        return $this->getData('is_salable');
    }

    /**
     * @param bool $useStock
     */
    public function setUseStock($useStock)
    {
        $this->setData('use_stock', $useStock);
    }

    /**
     * @return bool
     */
    public function getUseStock()
    {
        return $this->getData('use_stock');
    }

    /**
     * @param bool $backorders
     */
    public function setBackorders($backorders)
    {
        $this->setData('backorders', $backorders);
    }

    /**
     * @return bool
     */
    public function getBackorders()
    {
        return $this->getData('backorders');
    }

    /**
     * @param int $maximumOrderQuantity
     */
    public function setMaximumOrderQuantity($maximumOrderQuantity)
    {
        $this->setData('maximum_order_quantity', $maximumOrderQuantity);
    }

    /**
     * @return int
     */
    public function getMaximumOrderQuantity()
    {
        return $this->getData('maximum_order_quantity');
    }

    /**
     * @param int $minimumOrderQuantity
     */
    public function setMinimumOrderQuantity($minimumOrderQuantity)
    {
        $this->setData('minimum_order_quantity', $minimumOrderQuantity);
    }

    /**
     * @return int
     */
    public function getMinimumOrderQuantity()
    {
        return $this->setData('minimum_order_quantity');
    }
}

<?php

namespace Shopgate\Export\Helper\Product\Stock;

use Shopgate\Export\Model\Shopgate\Product\StockItem;

interface Utility
{
    public function getStockItem($product): StockItem;
}

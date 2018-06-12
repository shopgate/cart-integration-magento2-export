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

namespace Shopgate\Export\Helper\Cron;

use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Helper\Encoder;
use Shopgate\Base\Model\Shopgate\Order as ShopgateOrderModel;
use Shopgate\Base\Model\Utility\SgLoggerInterface;

class Utility
{
    /** @var SgLoggerInterface */
    private $log;
    /** @var Encoder */
    private $encoder;

    /**
     * @param SgLoggerInterface $logger
     * @param Encoder           $encoder
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        SgLoggerInterface $logger,
        Encoder $encoder
    ) {
        $this->log     = $logger;
        $this->encoder = $encoder;
    }

    /**
     * @param MagentoOrder $order
     *
     * @return bool
     */
    public function hasShippedItems($order)
    {
        $shippedItems = false;
        foreach ($order->getItemsCollection() as $orderItem) {
            /* @var $orderItem \Magento\Sales\Model\Order\Item */
            if ($orderItem->getQtyShipped() > 0) {
                $shippedItems = true;
                break;
            }
        }

        return $shippedItems;
    }

    /**
     * @param MagentoOrder $order
     *
     * @return bool
     */
    public function hasItemsToShip($order)
    {
        $itemsToShip = false;
        foreach ($order->getItemsCollection() as $orderItem) {
            /* @var $orderItem \Magento\Sales\Model\Order\Item */
            if ($orderItem->getQtyToShip() > 0 && $orderItem->getProductId() != null) {
                $itemsToShip = true;
                break;
            }
        }

        return $itemsToShip;
    }

    /**
     * @param array $items
     * @param int   $productID
     *
     * @return bool|\ShopgateOrderItem
     */
    public function findItemByProductId($items, $productID)
    {
        if (empty($productID) || empty($items)) {
            return false;
        }
        /** @var $item \ShopgateOrderItem */
        foreach ($items as $item) {
            $internalOrderInfo = $item->getInternalOrderInfo();
            if ($internalOrderInfo['product_id'] == $productID) {
                return $item;
            }
        }

        return false;
    }

    /**
     * @param ShopgateOrderModel $shopgateOrderModel
     *
     * @return \ShopgateOrder
     * @throws \ShopgateLibraryException
     */
    public function loadShopgateOrderData($shopgateOrderModel)
    {
        $shopgateOrder = new \ShopgateOrder($this->encoder->decode($shopgateOrderModel->getReceivedData()));

        if (!$shopgateOrder instanceof \ShopgateOrder) {
            throw new \ShopgateLibraryException(
                \ShopgateLibraryException::UNKNOWN_ERROR_CODE,
                "! (#{$shopgateOrderModel->getShopgateOrderNumber()})  unable to decode shopgate order object",
                true
            );
        }

        return $shopgateOrder;
    }
}

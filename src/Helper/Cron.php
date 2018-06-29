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

namespace Shopgate\Export\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\OrderRepository;
use Shopgate\Base\Model\Shopgate\Order as ShopgateOrderModel;
use Shopgate\Base\Model\Shopgate\OrderFactory as ShopgateOrderFactory;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Helper\Cron\Cancellation as CancellationHelper;
use Shopgate\Export\Helper\Cron\Shipping as ShippingHelper;

class Cron
{
    /** @var SgLoggerInterface */
    private $logger;
    /** @var ShopgateOrderFactory */
    private $shopgateOrderFactory;
    /** @var OrderRepository */
    private $orderRepository;
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;
    /** @var ShippingHelper */
    private $shippingHelper;
    /** @var CancellationHelper */
    private $cancellationHelper;

    /**
     * @param SgLoggerInterface     $logger
     * @param ShopgateOrderFactory  $orderFactory
     * @param OrderRepository       $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ShippingHelper        $shippingHelper
     * @param CancellationHelper    $cancellationHelper
     */
    public function __construct(
        SgLoggerInterface $logger,
        ShopgateOrderFactory $orderFactory,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ShippingHelper $shippingHelper,
        CancellationHelper $cancellationHelper
    ) {
        $this->logger                = $logger;
        $this->shopgateOrderFactory  = $orderFactory;
        $this->orderRepository       = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->shippingHelper        = $shippingHelper;
        $this->cancellationHelper    = $cancellationHelper;
    }

    /**
     * Iterates through all unsynchronized shopgate orders and sends
     * shippings including tracking numbers to Shopgate
     */
    public function setShippingCompleted()
    {
        /** @var \Shopgate\Base\Model\ResourceModel\Shopgate\Order\Collection $orderCollection */
        $orderCollection = $this->shopgateOrderFactory->create()->getCollection();
        $orderCollection->filterByUnsynchronizedOrders();
        $this->logger->debug("# Found {$orderCollection->getSize()} potential orders to send");

        /** @var ShopgateOrderModel $shopgateOrder */
        foreach ($orderCollection as $shopgateOrder) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $shopgateOrder->getOrderId(), 'eq')->create();
            $orderList      = $this->orderRepository->getList($searchCriteria);
            $magentoOrder   = $orderList->getFirstItem();
            $this->shippingHelper->sendShippingForOrder($shopgateOrder, $magentoOrder);
        }
    }
    /**
     * Iterates through all cancelled shopgate orders and sends
     * the cancellation back to Shopgate
     */
    public function cancelOrders()
    {
        /** @var \Shopgate\Base\Model\ResourceModel\Shopgate\Order\Collection $orderCollection */
        $orderCollection = $this->shopgateOrderFactory->create()->getCollection();
        $orderCollection->filterByCancelledOrders();
        $this->logger->debug("# Found {$orderCollection->getSize()} potential orders to send");

        foreach ($orderCollection as $shopgateOrder) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $shopgateOrder->getOrderId(), 'eq')->create();

            $orderList = $this->orderRepository->getList($searchCriteria);
            /** @var MagentoOrder $magentoOrder */
            $magentoOrder = $orderList->getFirstItem();

            if (!$magentoOrder->isCanceled()
                && $magentoOrder->getState() !== MagentoOrder::STATE_CLOSED
                && !$magentoOrder->hasCreditmemos()
            ) {
                continue;
            }
            $this->cancellationHelper->cancelOrder($shopgateOrder, $magentoOrder);
        }
    }
}

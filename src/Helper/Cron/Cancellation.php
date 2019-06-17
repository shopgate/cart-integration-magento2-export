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

use Exception;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection;
use Shopgate\Base\Api\Config\SgCoreInterface;
use Shopgate\Base\Helper\Initializer\MerchantApi as MerchantApiHelper;
use Shopgate\Base\Model\Shopgate\Order as ShopgateOrderModel;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Helper\Cron\Shipping as ShippingHelper;
use Shopgate\Export\Helper\Cron\Utility as CronHelper;
use ShopgateMerchantApi;
use ShopgateMerchantApiException;
use ShopgateOrder;

class Cancellation
{
    /** @var ManagerInterface */
    private $messageManager;
    /** @var SgLoggerInterface */
    private $logger;
    /** @var SgCoreInterface */
    private $sgCoreConfig;
    /** @var ShopgateMerchantApi */
    private $merchantApi;
    /** @var ShippingHelper */
    private $shippingHelper;
    /** @var CronHelper */
    private $cronHelper;
    /** @var HistoryFactory */
    private $historyFactory;
    /** @var OrderStatusHistoryRepositoryInterface */
    private $historyRepository;

    /**
     * @param ManagerInterface                      $messageManager
     * @param SgLoggerInterface                     $logger
     * @param SgCoreInterface                       $sgCoreConfig
     * @param MerchantApiHelper                     $merchantApiHelper
     * @param ShippingHelper                        $shippingHelper
     * @param CronHelper                            $cronHelper
     * @param HistoryFactory                        $historyFactory
     * @param OrderStatusHistoryRepositoryInterface $historyRepository
     */
    public function __construct(
        ManagerInterface $messageManager,
        SgLoggerInterface $logger,
        SgCoreInterface $sgCoreConfig,
        MerchantApiHelper $merchantApiHelper,
        ShippingHelper $shippingHelper,
        CronHelper $cronHelper,
        HistoryFactory $historyFactory,
        OrderStatusHistoryRepositoryInterface $historyRepository
    ) {
        $this->messageManager    = $messageManager;
        $this->logger            = $logger;
        $this->sgCoreConfig      = $sgCoreConfig;
        $this->shippingHelper    = $shippingHelper;
        $this->merchantApi       = $merchantApiHelper->buildMerchantApi();
        $this->cronHelper        = $cronHelper;
        $this->historyFactory    = $historyFactory;
        $this->historyRepository = $historyRepository;
    }

    /**
     * @param ShopgateOrderModel $shopgateOrderModel
     * @param MagentoOrder       $magentoOrder
     *
     * @throws Exception
     */
    public function cancelOrder($shopgateOrderModel, $magentoOrder)
    {
        if (!$this->sgCoreConfig->isValid()) {
            return;
        }

        $orderNumber = $shopgateOrderModel->getShopgateOrderNumber();

        try {
            $shopgateOrder     = $this->cronHelper->loadShopgateOrderData($shopgateOrderModel);
            $orderItems        = $magentoOrder->getItemsCollection();
            $cancellationItems = $this->getItemsForCancellation($orderItems, $shopgateOrder);
            $qtyCancelled      = $this->getCancelledItemCount($cancellationItems);

            if (empty($cancellationItems) && count($orderItems) > 0) {
                /** @var OrderStatusHistoryInterface $history */
                /** @noinspection PhpUndefinedMethodInspection */
                $history = $this->historyFactory->create();
                $comment =
                    '[SHOPGATE] Notice: Credit memo was not sent to Shopgate because no product quantity was affected.';
                $history->setParentId($magentoOrder->getId())
                        ->setComment($comment)
                        ->setEntityName('order')
                        ->setStatus($magentoOrder->getStatus());

                $this->historyRepository->save($history);

                return;
            }

            $cancelShippingCosts = !$this->cronHelper->hasShippedItems($magentoOrder);
            $fullCancellation    = $this->isFullCancellation(
                $qtyCancelled,
                $magentoOrder->getTotalQtyOrdered(),
                $cancelShippingCosts
            );

            $this->merchantApi->cancelOrder(
                $shopgateOrderModel->getShopgateOrderNumber(),
                $fullCancellation,
                $cancellationItems,
                $cancelShippingCosts,
                'Order was cancelled in Shopsystem!'
            );

            $this->finalizeCancellation($shopgateOrderModel, $magentoOrder);
        } catch (ShopgateMerchantApiException $e) {
            if ($e->getCode() === '222') {
                // order already canceled in shopgate
                $shopgateOrderModel->setIsCancellationSentToShopgate(true);
                $shopgateOrderModel->save();
            } else {
                // Received error from shopgate server
                $this->messageManager->addErrorMessage(
                    __(
                        '[SHOPGATE] An error occurred while trying to cancel the order at Shopgate.<br />Please contact Shopgate support.'
                    )
                );
                $this->messageManager->addErrorMessage("Error: {$e->getCode()} - {$e->getMessage()}");
                $this->logger->error(
                    "(#{$orderNumber})  SMA-Error on cancel order! Message: {$e->getCode()} - {$e->getMessage()}"
                );
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __('[SHOPGATE] An unknown error occurred!<br />Please contact Shopgate support.')
            );
            $this->logger->error(
                "(#{$orderNumber})  SMA-Error on cancel order! Message: {$e->getCode()} - {$e->getMessage()}"
            );
        }
    }

    /**
     * @param ShopgateOrderModel $shopgateOrderModel
     * @param MagentoOrder       $magentoOrder
     *
     * @throws Exception
     */
    protected function finalizeCancellation($shopgateOrderModel, $magentoOrder)
    {
        $this->messageManager->addSuccessMessage(__('[SHOPGATE] Order successfully cancelled at Shopgate.'));

        $shopgateOrderModel->setIsCancellationSentToShopgate(true);
        $shopgateOrderModel->save();

        if (!$shopgateOrderModel->getIsSentToShopgate()
            && !$this->shippingHelper->completeShipping(
                $shopgateOrderModel,
                $magentoOrder
            )
        ) {
            $this->logger->error(
                "! (#{$shopgateOrderModel->getShopgateOrderNumber()})  not sent to shopgate and shipping not complete"
            );
        }
    }

    /**
     * @param array $cancelledItems
     *
     * @return int
     */
    protected function getCancelledItemCount($cancelledItems): int
    {
        $qtyCancelled = 0;

        foreach ($cancelledItems as $cancelledItem) {
            $qtyCancelled += $cancelledItem['quantity'];
        }

        return $qtyCancelled;
    }

    /**
     * @param Collection    $orderItems
     * @param ShopgateOrder $shopgateOrder
     *
     * @return array
     */
    protected function getItemsForCancellation($orderItems, $shopgateOrder): array
    {
        $cancellationItems = [];

        /**  @var $orderItem Item */
        foreach ($orderItems as $orderItem) {
            $parentItem = $orderItem->getParentItem();
            $rdItem     = $this->cronHelper->findItemByProductId(
                $shopgateOrder->getItems(),
                $orderItem->getData('product_id')
            );
            $mainItem   =
                empty($parentItem) || ($parentItem && $parentItem->getProductType() !== Configurable::TYPE_CODE)
                    ? $orderItem
                    : $orderItem->getParentItem();

            if ($this->shouldCancelOrderItem($orderItem)
                && $rdItem
                && $mainItem
            ) {
                $cancellationItems[] = [
                    'item_number' => $rdItem->getItemNumber(),
                    'quantity'    => (int) $mainItem->getQtyCanceled() + (int) $mainItem->getQtyRefunded(),
                ];
            }
        }

        return $cancellationItems;
    }

    /**
     * @param Item $orderItem
     *
     * @return bool
     */
    protected function shouldCancelOrderItem($orderItem): bool
    {
        return $orderItem->getProductType() !== Configurable::TYPE_CODE
            && $orderItem->getQtyCanceled() + $orderItem->getQtyRefunded() > 0
            && !$orderItem->getIsVirtual();
    }

    /**
     * @param int  $qtyCancelled
     * @param int  $qtyOrdered
     * @param bool $cancelShippingCosts
     *
     * @return bool
     */
    protected function isFullCancellation($qtyCancelled, $qtyOrdered, $cancelShippingCosts): bool
    {
        return $qtyCancelled === $qtyOrdered && $cancelShippingCosts;
    }
}

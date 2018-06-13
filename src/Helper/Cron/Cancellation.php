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

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Api\Config\SgCoreInterface;
use Shopgate\Base\Helper\Initializer\MerchantApi as MerchantApiHelper;
use Shopgate\Base\Model\Shopgate\Order as ShopgateOrderModel;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Helper\Cron\Shipping as ShippingHelper;
use Shopgate\Export\Helper\Cron\Utility as CronHelper;

class Cancellation
{
    /** @var ManagerInterface */
    private $messageManager;
    /** @var SgLoggerInterface */
    private $logger;
    /** @var SgCoreInterface */
    private $sgCoreConfig;
    /** @var MerchantApiHelper */
    private $merchantApiHelper;
    /** @var \ShopgateMerchantApi */
    private $merchantApi;
    /** @var ShippingHelper */
    private $shippingHelper;
    /** @var CronHelper */
    private $cronHelper;

    /**
     * @param ManagerInterface  $messageManager
     * @param SgLoggerInterface $logger
     * @param SgCoreInterface   $sgCoreConfig
     * @param MerchantApiHelper $merchantApiHelper
     * @param ShippingHelper    $shippingHelper
     */
    public function __construct(
        ManagerInterface $messageManager,
        SgLoggerInterface $logger,
        SgCoreInterface $sgCoreConfig,
        MerchantApiHelper $merchantApiHelper,
        ShippingHelper $shippingHelper,
        CronHelper $cronHelper
    ) {
        $this->messageManager    = $messageManager;
        $this->logger            = $logger;
        $this->sgCoreConfig      = $sgCoreConfig;
        $this->shippingHelper    = $shippingHelper;
        $this->merchantApiHelper = $merchantApiHelper;
        $this->merchantApi       = $this->merchantApiHelper->buildMerchantApi();
        $this->cronHelper        = $cronHelper;
    }

    /**
     * @param ShopgateOrderModel $shopgateOrderModel
     * @param MagentoOrder       $magentoOrder
     *
     * @return bool
     * @throws \ShopgateLibraryException
     */
    public function cancelOrder($shopgateOrderModel, $magentoOrder)
    {
        if (!$this->sgCoreConfig->isValid()) {
            return false;
        }

        $orderNumber = $shopgateOrderModel->getShopgateOrderNumber();

        try {
            $shopgateOrder     = $this->cronHelper->loadShopgateOrderData($shopgateOrderModel);
            $orderItems        = $magentoOrder->getItemsCollection();
            $cancellationItems = $this->getItemsForCancellation($orderItems, $shopgateOrder);
            $qtyCancelled      = $this->getCancelledItemCount($cancellationItems);

            if (count($orderItems) > 0
                && empty($cancellationItems)
            ) {
                $magentoOrder->addStatusHistoryComment(
                    '[SHOPGATE] Notice: Credit memo was not sent to Shopgate because no product quantity was affected.'
                );
                $magentoOrder->save();

                return true;
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
        } catch (\ShopgateMerchantApiException $e) {
            if ($e->getCode() == '222') {
                // order already canceled in shopgate
                $shopgateOrderModel->setIsCancellationSentToShopgate(true);
                $shopgateOrderModel->save();
            } else {
                // Received error from shopgate server
                $this->messageManager->addErrorMessage(
                    __(
                        '[SHOPGATE] An error occured while trying to cancel the order at Shopgate.<br />Please contact Shopgate support.'
                    )
                );
                $this->messageManager->addErrorMessage("Error: {$e->getCode()} - {$e->getMessage()}");
                $this->logger->error(
                    "(#{$orderNumber})  SMA-Error on cancel order! Message: {$e->getCode()} - {$e->getMessage()}"
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('[SHOPGATE] An unknown error occured!<br />Please contact Shopgate support.')
            );
            $this->logger->error(
                "(#{$orderNumber})  SMA-Error on cancel order! Message: {$e->getCode()} - {$e->getMessage()}"
            );
        }
    }

    /**
     * @param ShopgateOrderModel $shopgateOrderModel
     * @param MagentoOrder       $magentoOrder
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
    protected function getCancelledItemCount($cancelledItems)
    {
        $qtyCancelled = 0;

        foreach ($cancelledItems as $cancelledItem) {
            $qtyCancelled += $cancelledItem['quantity'];
        }

        return $qtyCancelled;
    }

    /**
     * @param Magento\Sales\Model\ResourceModel\Order\Item\Collection $orderItems
     * @param \ShopgateOrder                                          $shopgateOrder
     *
     * @return array
     */
    protected function getItemsForCancellation($orderItems, $shopgateOrder)
    {
        $cancellationItems = [];

        /**  @var $orderItem \Magento\Sales\Model\Order\Item */
        foreach ($orderItems as $orderItem) {
            $rdItem   = $this->cronHelper->findItemByProductId(
                $shopgateOrder->getItems(),
                $orderItem->getData('product_id')
            );
            $mainItem = empty($orderItem->getParentItem()) || $orderItem->getParentItem()->getProductType() != Configurable::TYPE_CODE
                ? $orderItem
                : $orderItem->getParentItem();

            if ($this->shouldCancelOrderItem($orderItem)
                && $rdItem
            ) {
                $cancellationItems[] = [
                    'item_number' => $rdItem->getItemNumber(),
                    'quantity'    => intval($mainItem->getQtyCanceled()) + intval($mainItem->getQtyRefunded()),
                ];
            }
        }

        return $cancellationItems;
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $orderItem
     *
     * @return bool
     */
    protected function shouldCancelOrderItem($orderItem)
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
    protected function isFullCancellation($qtyCancelled, $qtyOrdered, $cancelShippingCosts)
    {
        return (empty($cancellationItems) || $qtyCancelled === $qtyOrdered) && $cancelShippingCosts;
    }
}

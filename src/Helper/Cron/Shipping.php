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

use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Shopgate\Base\Helper\Initializer\MerchantApi as MerchantApiHelper;
use Shopgate\Base\Model\Config;
use Shopgate\Base\Model\Shopgate\Order as ShopgateOrderModel;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Helper\Cron\Utility as CronHelper;
use ShopgateDeliveryNote;
use ShopgateMerchantApiException;

class Shipping
{
    /** @var Config */
    private $config;
    /** @var CronHelper */
    private $cronHelper;
    /** @var SgLoggerInterface */
    private $logger;
    /** @var \ShopgateMerchantApi */
    private $merchantApi;
    /** @var MerchantApiHelper */
    private $merchantApiHelper;
    /** @var ManagerInterface */
    private $messageManager;

    /**
     * @param Config            $config
     * @param Utility           $cronHelper
     * @param SgLoggerInterface $logger
     * @param MerchantApiHelper $merchantApiHelper
     * @param ManagerInterface  $messageManager
     */
    public function __construct(
        Config $config,
        CronHelper $cronHelper,
        SgLoggerInterface $logger,
        MerchantApiHelper $merchantApiHelper,
        ManagerInterface $messageManager
    ) {

        $this->config            = $config;
        $this->cronHelper        = $cronHelper;
        $this->logger            = $logger;
        $this->merchantApiHelper = $merchantApiHelper;
        $this->merchantApi       = $this->merchantApiHelper->buildMerchantApi();
        $this->messageManager    = $messageManager;
    }

    /**
     * @param ShopgateOrderModel $shopgateOrder
     * @param MagentoOrder       $magentoOrder
     */
    public function sendShippingForOrder($shopgateOrder, $magentoOrder)
    {
        $errors    = 0;
        $shipments = $magentoOrder->getShipmentsCollection();

        $this->logger->debug("# getTrackCollections from MagentoOrder (count: '" . $shipments->count() . "')");

        /* @var $shipment \Magento\Sales\Model\Order\Shipment */
        foreach ($shipments as $shipment) {
            $errors += $this->synchronizeShipment($shipment, $shopgateOrder);
        }

        if (!$this->completeShipping($shopgateOrder, $magentoOrder)) {
            $errors++;
        }

        if ($errors > 0) {
            $this->messageManager->addErrorMessage(
                __(
                    '[SHOPGATE] Order status was updated but %s errors occurred',
                    $errors['errorcount']
                )
            );
        } else {
            $this->messageManager->addSuccessMessage(
                __('[SHOPGATE] Order status was updated successfully at Shopgate')
            );
        }
    }

    /**
     * set shipping to complete for the shopgate order model
     *
     * @param $shopgateOrder ShopgateOrderModel
     * @param $order         MagentoOrder
     *
     * @return bool
     */
    public function completeShipping($shopgateOrder, $order)
    {
        $orderNumber = $shopgateOrder->getShopgateOrderNumber();

        if ($this->orderNotCompletelyShipped($order)) {
            return true;
        }

        try {
            $this->logger->debug(
                "> (#{$orderNumber}) Try to call SMA::setOrderShippingCompleted (Ordernumber: {$shopgateOrder->getShopgateOrderNumber()} )"
            );
            $this->merchantApi->setOrderShippingCompleted($shopgateOrder->getShopgateOrderNumber());
            $this->logger->debug("> (#{$orderNumber}) Call to SMA::setOrderShippingCompleted was successfull!");
        } catch (ShopgateMerchantApiException $e) {
            if ($e->getCode() == ShopgateMerchantApiException::ORDER_SHIPPING_STATUS_ALREADY_COMPLETED
                || $e->getCode() == ShopgateMerchantApiException::ORDER_ALREADY_COMPLETED
            ) {
                $this->messageManager->addNoticeMessage(
                    __('[SHOPGATE] The order status is already set to "shipped" at Shopgate!')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __(
                        '[SHOPGATE] An error occured while updating the shipping status.<br />Please contact Shopgate support.'
                    )
                );
                $this->messageManager->addErrorMessage("{$e->getCode()} - {$e->getMessage()}");
                $this->logger->debug(
                    "! (#{$orderNumber})  SMA-Error on set shipping complete! Message: {$e->getCode()} - {$e->getMessage()}"
                );
                $this->logger->error(
                    "(#{$orderNumber}) SMA-Error on set shipping complete! Message: {$e->getCode()} - {$e->getMessage()}"
                );

                return false;
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('[SHOPGATE] An unknown error occured!<br />Please contact Shopgate support.')
            );
            $this->logger->debug(
                "! (#{$orderNumber}) unknown error on set shipping complete! Message: {$e->getCode()} - {$e->getMessage()}"
            );
            $this->logger->error(
                "(#{$orderNumber}) Unkwon error on set shipping complete! Message: {$e->getCode()} - {$e->getMessage()}"
            );

            return false;
        }

        $shopgateOrder->setIsSentToShopgate(true);
        $shopgateOrder->save();

        return true;
    }

    /**
     * @param $order MagentoOrder
     *
     * @return bool
     */
    protected function orderNotCompletelyShipped($order)
    {
        $isShipmentComplete = $this->cronHelper->hasShippedItems($order);

        if ($this->cronHelper->hasItemsToShip($order)) {
            $isShipmentComplete = false;
        }

        if ($order->getState() == MagentoOrder::STATE_COMPLETE) {
            $isShipmentComplete = true;
        }

        if (!$isShipmentComplete) {
            return true;
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param ShopgateOrderModel                  $shopgateOrder
     *
     * @return int
     */
    protected function synchronizeShipment($shipment, $shopgateOrder)
    {
        $errorCount        = 0;
        $notes             = $this->generateNotesByShipment($shipment);
        $orderNumber       = $shopgateOrder->getShopgateOrderNumber();
        $reportedShipments = $shopgateOrder->getReportedShippingCollections();

        foreach ($notes as $note) {

            if (in_array($shipment->getId(), $reportedShipments)) {
                continue;
            }

            try {
                $this->logger->debug(
                    "> Try to call SMA::addOrderDeliveryNote (Ordernumber: {$orderNumber} )"
                );
                $this->merchantApi->addOrderDeliveryNote(
                    $orderNumber,
                    $note['service'],
                    $note['tracking_number']
                );
                $this->logger->debug('> Call to SMA::addOrderDeliveryNote was successfull!');
                $reportedShipments[] = $shipment->getId();
            } catch (ShopgateMerchantApiException $e) {

                if ($e->getCode() == ShopgateMerchantApiException::ORDER_SHIPPING_STATUS_ALREADY_COMPLETED
                    || $e->getCode() == ShopgateMerchantApiException::ORDER_ALREADY_COMPLETED
                ) {
                    $reportedShippments[] = $shipment->getId();
                } else {
                    $errorCount++;
                    $this->logger->error(
                        "(#{$orderNumber}) SMA-Error on add delivery note! Message: {$e->getCode()} - {$e->getMessage()}"
                    );
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    "(#{$orderNumber}) SMA-Error on add delivery note! Message: {$e->getCode()} - {$e->getMessage()}"
                );
                $errorCount++;
            }
        }

        $shopgateOrder->setReportedShippingCollections($reportedShipments);
        $shopgateOrder->save();

        return $errorCount;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     *
     * @return array
     */
    protected function generateNotesByShipment($shipment)
    {
        /** @var \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\Collection $tracks */
        $tracks = $shipment->getTracksCollection();
        $notes  = [];

        $this->logger->debug(
            "# getTrackCollections from MagentoOrderShippment (count: '" . $tracks->count() . "')"
        );

        if ($tracks->count() == 0) {
            $notes[] = ['service' => ShopgateDeliveryNote::OTHER, 'tracking_number' => ''];
        }

        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        foreach ($tracks as $track) {

            switch ($track->getCarrierCode()) {
                case 'fedex':
                    $carrier = ShopgateDeliveryNote::FEDEX;
                    break;
                case 'usps':
                    $carrier = ShopgateDeliveryNote::USPS;
                    break;
                case 'ups':
                    $carrier = ShopgateDeliveryNote::UPS;
                    break;
                case 'dhlint':
                case 'dhl':
                    $carrier = ShopgateDeliveryNote::DHL;
                    break;
                default:
                    $carrier = ShopgateDeliveryNote::OTHER;
                    break;
            }

            $notes[] = ['service' => $carrier, 'tracking_number' => $track->getNumber()];
        }

        return $notes;
    }
}

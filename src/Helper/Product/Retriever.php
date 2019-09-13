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

namespace Shopgate\Export\Helper\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Base\Model\Utility\SgProfiler;
use Shopgate\Export\Model\Export\Product;
use Shopgate\Export\Model\Export\ProductFactory as ExportFactory;

class Retriever
{
    /**  @const ALLOWED_PRODUCT_TYPES   Supported product types for export */
    const ALLOWED_PRODUCT_TYPES = [Type::TYPE_SIMPLE, Configurable::TYPE_CODE, Grouped::TYPE_CODE];
    /** @var SgLoggerInterface */
    private $log;
    /** @var SgProfiler */
    private $profiler;
    /** @var ProductFactory */
    private $productFactory;
    /** @var ExportFactory */
    private $exportFactory;

    /**
     * @param SgLoggerInterface $logger
     * @param ProductFactory    $productFactory
     * @param ExportFactory     $exportFactory
     * @param SgProfiler        $profiler
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        SgLoggerInterface $logger,
        ProductFactory $productFactory,
        ExportFactory $exportFactory,
        SgProfiler $profiler
    ) {
        $this->log            = $logger;
        $this->productFactory = $productFactory;
        $this->exportFactory  = $exportFactory;
        $this->profiler       = $profiler;
    }

    /**
     * @param null | int $limit
     * @param null | int $offset
     * @param int[]      $uids
     * @param int[]      $skipItemIds
     *
     * @return array
     */
    public function getItems($limit = null, $offset = null, array $uids = [], array $skipItemIds = [])
    {
        $this->log->access('Start Product Export...');
        $this->log->debug('Start Product Export...');
        $profiler = $this->profiler->start();
        $export   = [];

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
        $productCollection = $this->productFactory->create()->getCollection();
        $productCollection->addAttributeToFilter(
            'visibility',
            [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH
            ]
        );
        $productCollection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $productCollection->addAttributeToFilter('type_id', ['in' => self::ALLOWED_PRODUCT_TYPES]);
        $productCollection->addStoreFilter();

        if (!empty($skipItemIds)) {
            $productCollection->addAttributeToFilter('entity_id', ['nin' => $skipItemIds]);
        }

        if (!empty($uids)) {
            $productCollection->addAttributeToFilter('entity_id', ['in' => $uids]);
        } elseif (null !== $limit && null !== $offset) {
            $productCollection->getSelect()->limit($limit, $offset);
            $this->log->debug("Product Export Limit: {$limit}");
            $this->log->debug("Product Export Offset: {$offset}");
        }

        foreach ($productCollection as $product) {
            $this->log->debug("Start Collection Product Load With ID: {$product->getId()}");
            $product = $this->productFactory->create()->load($product->getId());

            /** @var Product $productExportModel */
            $productExportModel = $this->exportFactory->create();
            try {
                $productExportModel->setItem($product);
                $export[] = $productExportModel->generateData();
                $product->clearInstance();
            } catch (\Exception $e) {
                $this->log->error(
                    "Skipping export of product with id: {$product->getId()}, message: " . $e->getMessage()
                );
            }
        }

        $profiler->end()->debug('Export Product duration %s seconds');
        $this->log->access('End Product Export...');
        $this->log->debug('End Product Export...');

        return $export;
    }
}

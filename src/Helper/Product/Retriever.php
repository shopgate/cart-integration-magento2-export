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

declare(strict_types=1);

namespace Shopgate\Export\Helper\Product;

use Exception;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Bundle\Model\Product\Type as BundleType;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Base\Model\Utility\SgProfiler;
use Shopgate\Export\Api\ExportInterface;
use Shopgate\Export\Model\Export\Product;
use Shopgate\Export\Model\Export\ProductFactory as ExportFactory;

class Retriever
{
    /**  @const ALLOWED_PRODUCT_TYPES   Supported product types for export */
    public const ALLOWED_PRODUCT_TYPES = [
        Type::TYPE_SIMPLE,
        Configurable::TYPE_CODE,
        Grouped::TYPE_CODE,
        BundleType::TYPE_CODE
    ];
    /** @var SgLoggerInterface */
    private $log;
    /** @var SgProfiler */
    private $profiler;
    /** @var ExportFactory */
    private $exportFactory;
    /** @var ProductRepository */
    private $productRepository;
    /** @var CollectionFactory */
    private $collectionFactory;
    /** @var CoreInterface */
    private $scopeConfig;

    /**
     * @param SgLoggerInterface $logger
     * @param ExportFactory     $exportFactory
     * @param SgProfiler        $profiler
     * @param ProductRepository $productRepository
     * @param CollectionFactory $productCollectionFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        SgLoggerInterface $logger,
        ExportFactory $exportFactory,
        SgProfiler $profiler,
        ProductRepository $productRepository,
        CollectionFactory $productCollectionFactory,
        CoreInterface $scopeConfig
    ) {
        $this->log               = $logger;
        $this->exportFactory     = $exportFactory;
        $this->profiler          = $profiler;
        $this->productRepository = $productRepository;
        $this->collectionFactory = $productCollectionFactory;
        $this->scopeConfig       = $scopeConfig;
    }

    /**
     * @param null | int $limit
     * @param null | int $offset
     * @param int[]      $uids
     * @param int[]      $skipItemIds
     *
     * @return array
     * @throws LocalizedException
     */
    public function getItems($limit = null, $offset = null, array $uids = [], array $skipItemIds = []): array
    {
        $this->log->access('Start Product Export...');
        $this->log->debug('Start Product Export...');
        $profiler = $this->profiler->start();
        $export   = [];

        /** @var Collection $productCollection */
        $productCollection = $this->collectionFactory->create();
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

        if ($this->scopeConfig->getConfigByPath(ExportInterface::PATH_PROD_OUT_OF_STOCK)->getData('value')) {
            $productCollection->setFlag('has_stock_status_filter', false);
        }

        foreach ($productCollection as $product) {
            $this->log->debug("Start Collection Product Load With ID: {$product->getId()}");
            $item = $this->productRepository->getById($product->getId());

            /** @var Product $productExportModel */
            $productExportModel = $this->exportFactory->create();
            try {
                $productExportModel->setItem($item);
                $export[] = $productExportModel->generateData();
            } catch (Exception $error) {
                $this->log->error(
                    "Skipping export of product with id: {$item->getId()}, message: " . $error->getMessage()
                );
            }
        }

        $profiler->end()->debug('Export Product duration %s seconds');
        $this->log->access('End Product Export...');
        $this->log->debug('End Product Export...');

        return $export;
    }
}

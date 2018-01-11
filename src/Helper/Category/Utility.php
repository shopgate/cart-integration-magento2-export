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

namespace Shopgate\Export\Helper\Category;

use Magento\Catalog\Model\CategoryFactory;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Model\Export\Category;
use Shopgate\Export\Model\Export\CategoryFactory as ExportFactory;

class Utility
{
    /** @var SgLoggerInterface */
    private $log;
    /** @var CategoryFactory */
    private $categoryFactory;
    /** @var ExportFactory */
    private $exportFactory;

    /**
     * @param CategoryFactory   $categoryFactory
     * @param ExportFactory     $exportFactory
     * @param SgLoggerInterface $logger
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        ExportFactory $exportFactory,
        SgLoggerInterface $logger
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->exportFactory   = $exportFactory;
        $this->log             = $logger;
    }

    /**
     * Traverses category tree and exports a set of Shopgate Categories
     *
     * @param int         $parentId - root category to use
     * @param null|array  $uIds     - UID's to export
     * @param null|string $offset   - offset position to export from
     * @param null|string $limit    - limit of products to export
     *
     * @return Category[]
     */
    public function buildCategoryTree($parentId, $uIds, $offset, $limit)
    {
        $this->log->debug('Build Tree with Parent-ID: ' . $parentId);

        if (empty($uIds)) {
            $category = $this->categoryFactory->create()->load($parentId);
            /** @var \Magento\Catalog\Model\ResourceModel\Category\Tree $tree */
            /** @noinspection PhpParamsInspection */
            $rootNode = $category->getTreeModel()->load()->getNodeById($parentId);
            $uIds     = $category->getTreeModel()->getChildren($rootNode);
        }

        if (!is_null($offset) && !is_null($offset)) {
            $uIds = array_slice($uIds, $offset, $limit);
        }

        $export      = [];
        $maxPosition = $this->getMaximumCategoryPosition();

        foreach ($uIds as $categoryId) {
            $this->log->debug('Load Category with ID: ' . $categoryId);
            /** @var \Magento\Catalog\Model\Category $category */
            $category            = $this->categoryFactory->create()->load($categoryId);
            $categoryExportModel = $this->exportFactory->create();
            $categoryExportModel->setItem($category);
            $categoryExportModel->setParentId($parentId);
            $categoryExportModel->setMaximumPosition($maxPosition);
            $export[] = $categoryExportModel->generateData();
        }

        return $export;
    }

    /**
     * Retrieves the maximum category number + 100
     *
     * @return int
     */
    private function getMaximumCategoryPosition()
    {
        $maxCategoryPosition = $this->categoryFactory->create()
                                                     ->getCollection()
                                                     ->setOrder('position', 'DESC')
                                                     ->getFirstItem()
                                                     ->getData('position');
        $this->log->debug('Max Category Position: ' . $maxCategoryPosition);

        return $maxCategoryPosition + 100;
    }
}

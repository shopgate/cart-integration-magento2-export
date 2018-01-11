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

use Magento\Store\Model\StoreManagerInterface;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Model\Export\Category;

class Retriever
{
    /** @var SgLoggerInterface */
    private $log;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var Utility */
    private $utility;

    /**
     * @param SgLoggerInterface     $logger
     * @param StoreManagerInterface $storeManager
     * @param Utility               $utility
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        SgLoggerInterface $logger,
        StoreManagerInterface $storeManager,
        Utility $utility
    ) {
        $this->log          = $logger;
        $this->storeManager = $storeManager;
        $this->utility      = $utility;
    }

    /**
     * @see \Shopgate\Export\Api\ExportInterface::getCategories
     *
     * @param null | string $limit
     * @param null | string $offset
     * @param array         $uids
     *
     * @return Category[]
     */
    public function getCategories($limit = null, $offset = null, array $uids = [])
    {
        $this->log->access('Start Category Export...');
        $this->log->debug('Start Category Export...');

        $rootCatId = $this->storeManager->getGroup()->getRootCategoryId();

        $this->log->debug('Root-Category-Id: ' . $rootCatId);
        $this->log->debug('Start Category-Tree Build ...');

        $export = $this->utility->buildCategoryTree($rootCatId, $uids, $offset, $limit);

        $this->log->debug('End Category-Tree Build...');
        $this->log->access('Finished Category Export...');
        $this->log->debug('Finished Category Export...');

        return $export;
    }
}

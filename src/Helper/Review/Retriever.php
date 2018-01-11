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

namespace Shopgate\Export\Helper\Review;

use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Review\Model\Review as MageReview;
use Magento\Store\Model\StoreManagerInterface;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Base\Model\Utility\SgProfiler;
use Shopgate\Export\Model\Export\Review;
use Shopgate\Export\Model\Export\ReviewFactory as ExportFactory;

class Retriever
{
    /** @var SgLoggerInterface */
    private $log;
    /** @var SgProfiler */
    private $profiler;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var ExportFactory */
    private $exportFactory;
    /** @var CollectionFactory */
    private $reviewCollectionFactory;

    /**
     * @param SgLoggerInterface     $logger
     * @param StoreManagerInterface $storeManager
     * @param SgProfiler            $profiler
     * @param ExportFactory         $exportFactory
     * @param CollectionFactory     $reviewCollectionFactory
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        SgLoggerInterface $logger,
        StoreManagerInterface $storeManager,
        SgProfiler $profiler,
        ExportFactory $exportFactory,
        CollectionFactory $reviewCollectionFactory
    ) {
        $this->log                     = $logger;
        $this->storeManager            = $storeManager;
        $this->profiler                = $profiler;
        $this->exportFactory           = $exportFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
    }

    /**
     * @see \Shopgate\Export\Api\ExportInterface::getReviews()
     *
     * @param null | string $limit
     * @param null | string $offset
     * @param array         $uids
     *
     * @return Review[]
     */
    public function getReviews($limit = null, $offset = null, array $uids = [])
    {
        $this->log->access('Start Review Export...');
        $this->log->debug('Start Review Export...');

        $profiler = $this->profiler->start();
        $export   = [];

        /** @var \Magento\Review\Model\ResourceModel\Review\Collection $reviewCollection */
        $reviewCollection = $this->reviewCollectionFactory->create();
        $reviewCollection->addStoreFilter($this->storeManager->getStore()->getId());
        $reviewCollection->addStatusFilter(MageReview::STATUS_APPROVED);

        if (!empty($uids)) {
            $reviewCollection->addFilter('entity_pk_value', ['in' => $uids]);
        } elseif (!is_null($offset) && !is_null($offset)) {
            $reviewCollection->getSelect()->limit($limit, $offset);
            $this->log->debug("Review Export Limit: {$limit}");
            $this->log->debug("Review Export Offset: {$offset}");
        }
        $reviewCollection->load()->addRateVotes();
        foreach ($reviewCollection as $review) {
            $this->log->debug("Start Collection Product Load With ID: {$review->getId()}");

            /** @var Review $reviewExportModel */
            $reviewExportModel = $this->exportFactory->create();

            try {
                $reviewExportModel->setItem($review);
                $export[] = $reviewExportModel->generateData();
                $review->clearInstance();
            } catch (\Exception $e) {
                $this->log->error(
                    "Skipping export of review with id: {$review->getId()}, message: " . $e->getMessage()
                );
            }
        }

        $profiler->end()->debug('Review export duration %s seconds');
        $this->log->access('Finished Review Export...');
        $this->log->debug('Finished Review Export...');

        return $export;
    }
}

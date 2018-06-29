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

namespace Shopgate\Export\Model\Service;

use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Api\CronInterface;
use Shopgate\Export\Helper\Cron as CronHelper;

class Cron implements CronInterface
{
    /** @var SgLoggerInterface */
    private $logger;
    /** @var CronHelper */
    private $cronHelper;

    /**
     * @param SgLoggerInterface $logger
     * @param CronHelper        $cron
     */
    public function __construct(
        SgLoggerInterface $logger,
        CronHelper $cron
    ) {
        $this->logger     = $logger;
        $this->cronHelper = $cron;
    }

    /**
     * @inheritdoc
     */
    public function cron($jobname, $params, &$message, &$errorcount)
    {
        $this->logger->debug('Start Run CRON-Jobs');
        $this->logger->debug('# Run job {$jobname}');
        switch ($jobname) {
            case CronInterface::CRON_ACTION_SHIPPING_COMPLETED:
                $this->cronHelper->setShippingCompleted();
                break;
            case CronInterface::CRON_ACTION_CANCEL_ORDERS:
                $this->cronHelper->cancelOrders();
                break;
            default:
                throw new \ShopgateLibraryException(
                    \ShopgateLibraryException::PLUGIN_CRON_UNSUPPORTED_JOB,
                    '"' . $jobname . '"',
                    true
                );
        }

        $this->logger->debug('END Run CRON-Jobs');
    }
}

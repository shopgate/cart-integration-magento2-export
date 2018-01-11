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

namespace Shopgate\Export\Helper\Customer;

use Magento\Customer\Model\AccountManagement;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\InvalidEmailOrPasswordException;
use Shopgate\Base\Helper\Customer\Utility;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use ShopgateLibraryException;

class Retriever
{
    /** @var AccountManagement */
    protected $accountManagement;
    /** @var SgLoggerInterface */
    private $log;
    /** @var Utility */
    private $utility;

    /**
     * @param AccountManagement $accountManagement
     * @param SgLoggerInterface $sgLoggerInterface
     * @param Utility           $utility
     */
    public function __construct(
        AccountManagement $accountManagement,
        SgLoggerInterface $sgLoggerInterface,
        Utility $utility
    ) {
        $this->accountManagement = $accountManagement;
        $this->log               = $sgLoggerInterface;
        $this->utility           = $utility;
    }

    /**
     * @param string $user - The user name the customer entered at Shopgate Connect.
     * @param string $pass - The password the customer entered at Shopgate Connect.
     *
     * @return \ShopgateCustomer
     * @throws \ShopgateLibraryException
     */
    public function getCustomer($user, $pass)
    {
        try {
            $magentoCustomer = $this->accountManagement->authenticate($user, $pass);
        } catch (InvalidEmailOrPasswordException $e) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD,
                null,
                false,
                false
            );
        } catch (EmailNotConfirmedException $e) {
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_CUSTOMER_ACCOUNT_NOT_CONFIRMED,
                null,
                false,
                false
            );
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            throw new ShopgateLibraryException(
                ShopgateLibraryException::PLUGIN_CUSTOMER_UNKNOWN_ERROR,
                null,
                false,
                false
            );
        }
        $shopgateCustomer = $this->utility->loadByMagentoCustomer($magentoCustomer);

        return $shopgateCustomer;
    }
}

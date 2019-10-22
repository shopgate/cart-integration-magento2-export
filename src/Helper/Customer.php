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

namespace Shopgate\Export\Helper;

use Shopgate\Base\Helper\Customer as CustomerHelper;
use Shopgate\Base\Model\Shopgate\Extended\Base;
use \ShopgateCartCustomer;
use \ShopgateCartCustomerGroup;

class Customer
{
    /** @var Base */
    private $sgBase;
    /** @var CustomerHelper */
    private $customerHelper;

    /**
     * @param Base           $sgBase
     * @param CustomerHelper $customerHelper
     */
    public function __construct(
        Base $sgBase,
        CustomerHelper $customerHelper
    ) {
        $this->sgBase         = $sgBase;
        $this->customerHelper = $customerHelper;
    }

    /**
     * @return ShopgateCartCustomer
     */
    public function getCustomer()
    {
        $id = $this->sgBase->getExternalCustomerId();

        if (empty($id)) {
            return null;
        }

        $magentoCustomer = $this->customerHelper->getById($id);
        $shopgateCartCustomer = new ShopgateCartCustomer();
        $shopgateCartCustomer->setCustomerGroups($this->mapMagentoCustomerGroups($magentoCustomer->getGroupId()));
        $shopgateCartCustomer->setCustomerTaxClassKey($magentoCustomer->getTaxvat());

        return $shopgateCartCustomer;
    }

    /**
     * @param $groupId
     * @return ShopgateCartCustomerGroup[]
     */
    public function mapMagentoCustomerGroups($groupId)
    {
        $customerGroup = new ShopgateCartCustomerGroup();
        $customerGroup->setId($groupId);

        return [$customerGroup];
    }
}

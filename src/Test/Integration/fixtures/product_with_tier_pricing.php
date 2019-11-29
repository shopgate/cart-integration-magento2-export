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
 * @copyright 2019 Shopgate Inc
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Model\GroupManagement;
use Magento\TestFramework\Helper\Bootstrap;

/** @var $product Product */
$product = Bootstrap::getObjectManager()->create(Product::class);
$product->setTypeId('simple')
        ->setAttributeSetId(4)
        ->setWebsiteIds([1])
        ->setName('Simple Product')
        ->setPrice(1000)
        ->setFinalPrice(1000)
        ->setSku('simple-test-product')
        ->setVisibility(Visibility::VISIBILITY_BOTH)
        ->setStatus(Status::STATUS_ENABLED)
        ->setWeight(15.5)
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
        ->setTierPrice(
            [
                [
                    'website_id'       => 0,
                    'cust_group'       => GroupManagement::CUST_GROUP_ALL,
                    'price_qty'        => 2,
                    'price'            => 8,
                    'percentage_value' => 0
                ],
                [
                    'website_id'       => 0,
                    'cust_group'       => 1,
                    'price_qty'        => 5,
                    'price'            => 50,
                    'percentage_value' => 50
                ],
                [
                    'website_id'       => 0,
                    'cust_group'       => GroupManagement::NOT_LOGGED_IN_ID,
                    'price_qty'        => 3,
                    'price'            => 20,
                    'percentage_value' => 0
                ],
            ]
        )
        ->save();

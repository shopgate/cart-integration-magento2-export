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

use Shopgate\Base\Model\Shopgate\Extended\Base;
use Shopgate\Export\Api\ExportInterface;
use Shopgate\Export\Helper\Cart;
use Shopgate\Export\Helper\Category\Retriever as CategoryRetriever;
use Shopgate\Export\Helper\Customer\Retriever as CustomerRetriever;
use Shopgate\Export\Helper\Product\Retriever as ProductRetriever;
use Shopgate\Export\Helper\Review\Retriever as ReviewRetriever;

class Export implements ExportInterface
{
    /** @var CustomerRetriever */
    protected $customerHelper;
    /** @var CategoryRetriever */
    private $categoryRetriever;
    /** @var ProductRetriever */
    private $productRetriever;
    /** @var ReviewRetriever */
    private $reviewRetriever;
    /** @var Cart */
    private $cartHelper;
    /** @var Base */
    private $sgBase;

    /**
     * @param Cart              $cartHelper
     * @param CategoryRetriever $categoryRetriever
     * @param ProductRetriever  $productRetriever
     * @param CustomerRetriever $customerHelper
     * @param ReviewRetriever   $reviewRetriever
     * @param Base              $base
     *
     * @codeCoverageIgnore
     */
    public function __construct(
        Cart $cartHelper,
        CategoryRetriever $categoryRetriever,
        ProductRetriever $productRetriever,
        CustomerRetriever $customerHelper,
        ReviewRetriever $reviewRetriever,
        Base $base
    ) {
        $this->cartHelper        = $cartHelper;
        $this->categoryRetriever = $categoryRetriever;
        $this->productRetriever  = $productRetriever;
        $this->customerHelper    = $customerHelper;
        $this->reviewRetriever   = $reviewRetriever;
        $this->sgBase            = $base;
    }

    /**
     * @inheritdoc
     */
    public function getCategories($action, $shopNumber, $traceId, $limit = null, $offset = null, $uids = [])
    {
        $categories = [];
        $rawCats    = $this->getCategoriesRaw($limit, $offset, $uids);
        foreach ($rawCats as $category) {
            $categories[] = $category->asArray();
        }

        return $categories;
    }

    /**
     * @inheritdoc
     */
    public function getCategoriesRaw($limit = null, $offset = null, array $uids = [])
    {
        return $this->categoryRetriever->getCategories($limit, $offset, $uids);
    }

    /**
     * @inheritdoc
     */
    public function getItems($action, $shopNumber, $traceId, $limit = null, $offset = null, array $uids = [])
    {
        $products = [];
        foreach ($this->getItemsRaw($limit, $offset, $uids) as $product) {
            $products[] = $product->asArray();
        }

        return $products;
    }

    /**
     * @inheritdoc
     */
    public function getItemsRaw($limit = null, $offset = null, array $uids = [], array $skipItemIds = [])
    {
        return $this->productRetriever->getItems($limit, $offset, $uids, $skipItemIds);
    }

    /**
     * @inheritdoc
     */
    public function getReviews($action, $shopNumber, $traceId, $limit = null, $offset = null, $uids = [])
    {
        $reviews = [];
        foreach ($this->getReviewsRaw($limit, $offset, $uids) as $review) {
            $reviews[] = $review->asArray();
        }

        return $reviews;
    }

    /**
     * @inheritdoc
     */
    public function getReviewsRaw($limit = null, $offset = null, array $uids = [])
    {
        return $this->reviewRetriever->getReviews($limit, $offset, $uids);
    }

    /**
     * @inheritdoc
     */
    public function getCustomer($user, $pass)
    {
        $customer = $this->getCustomerRaw($user, $pass);

        return $customer->toArray();
    }

    /**
     * @inheritdoc
     */
    public function getCustomerRaw($user, $pass)
    {
        return $this->customerHelper->getCustomer($user, $pass);
    }

    /**
     * @inheritdoc
     */
    public function checkCart(array $cart)
    {
        $this->sgBase->loadArray($cart['cart']);
        $items = [];
        foreach ($this->checkCartRaw($this->sgBase) as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $cartObject) {
                    if (method_exists($cartObject, 'toArray')) {
                        $items[$key][] = $cartObject->toArray();
                    } else {
                        $items[$key][] = $cartObject;
                    }
                }
            } elseif (method_exists($value, 'toArray')) {
                $items[$key] = $value->toArray();
            } else {
                $items[$key] = $value;
            }
        }

        return $items;
    }

    /**
     * Rewrite cart object, effectively injecting it into the system
     *
     * @inheritdoc
     */
    public function checkCartRaw($cart)
    {
        $this->sgBase->loadArray($cart->toArray());

        return $this->cartHelper->loadSupportedMethods();
    }

    /**
     * Rewrite cart object, effectively injecting it into the system
     *
     * @inheritdoc
     */
    public function checkStockRaw($cart)
    {
        $this->sgBase->loadArray($cart->toArray());
        $order = $this->cartHelper->loadSupportedMethodsCheckStock();

        return $order['items'];
    }
}

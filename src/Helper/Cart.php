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

use Magento\Framework\Api\SimpleDataObjectConverter;
use Shopgate\Base\Model\Config;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Helper\Quote as QuoteHelper;
use Shopgate\Export\Helper\Customer as CustomerHelper;

class Cart
{
    /** @var Config */
    private $config;
    /** @var SgLoggerInterface */
    private $logger;
    /** @var QuoteHelper */
    private $quoteHelper;
    /** @var CustomerHelper */
    private $customerHelper;
    /** @var array */
    private $quoteFields;
    /** @var array */
    private $quoteStockFields;

    /**
     * @param Config            $config
     * @param SgLoggerInterface $logger
     * @param QuoteHelper       $quoteHelper
     * @param CustomerHelper    $customerHelper
     * @param array             $quoteFields
     * @param array             $quoteStockFields
     */
    public function __construct(
        Config $config,
        SgLoggerInterface $logger,
        QuoteHelper $quoteHelper,
        CustomerHelper $customerHelper,
        array $quoteFields = [],
        array $quoteStockFields = []
    ) {
        $this->config           = $config;
        $this->logger           = $logger;
        $this->quoteHelper      = $quoteHelper;
        $this->customerHelper   = $customerHelper;
        $this->quoteFields      = $quoteFields;
        $this->quoteStockFields = $quoteStockFields;
    }

    /**
     * Takes in the allowed cart methods loaded up in the Base's DI
     *
     * @return array
     * @throws \Exception
     */
    public function loadSupportedMethods()
    {
        $this->quoteHelper->load($this->quoteFields);
        $fields = $this->loadMethods($this->config->getSupportedFieldsCheckCart());
        $this->quoteHelper->cleanup();

        return $fields;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function loadSupportedMethodsCheckStock()
    {
        $this->quoteHelper->load($this->quoteStockFields);
        $fields = $this->loadMethods($this->quoteStockFields);
        $this->quoteHelper->cleanup();

        return $fields;
    }

    /**
     * Loads the methods of the current class
     *
     * @param array $fields
     *
     * @return array
     */
    private function loadMethods(array $fields)
    {
        $methods = [];
        foreach ($fields as $rawField) {
            $method = 'get' . SimpleDataObjectConverter::snakeCaseToUpperCamelCase($rawField);
            $this->logger->debug('Starting method ' . $method);
            $methods[$rawField] = $this->{$method}();
            $this->logger->debug('Finished method ' . $method);
        }

        return $methods;
    }

    /**
     * @return \ShopgateShippingMethod[]
     */
    protected function getShippingMethods()
    {
        return $this->quoteHelper->getValidatedShippingMethods();
    }

    /**
     * @return string
     */
    protected function getCurrency()
    {
        //todo-sg: get currency of current shop, can also implement in the main Config as it falls back to that
    }

    /**
     * @return \ShopgateCartCustomer
     */
    protected function getCustomer()
    {
        return $this->customerHelper->getCustomer();
    }

    /**
     * @return \ShopgateCartItem[]
     * @throws \Exception
     */
    protected function getItems()
    {
        return $this->quoteHelper->getValidatedItems();
    }

    /**
     * @return \ShopgateExternalCoupon[]
     */
    protected function getExternalCoupons()
    {
        return $this->quoteHelper->getValidatedCoupons();
    }

    /**
     * @return \ShopgatePaymentMethod[]
     */
    protected function getPaymentMethods()
    {
        //todo-sg: return payment methods available for this cart
    }

    /**
     * @return string
     */
    protected function getInternalCartInfo()
    {
        return '{}';
    }
}

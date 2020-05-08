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

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Utility\SgLoggerInterface;

class Tax
{
    /** @var CoreInterface */
    private $config;
    /** @var Calculation */
    private $calculation;
    /** @var TaxHelper */
    private $taxHelper;
    /** @var SgLoggerInterface */
    private $logger;
    /** @var Session */
    private $customerSession;
    /** @var GroupRepositoryInterface */
    private $groupRepository;

    /**
     * @param CoreInterface            $sgCore
     * @param Calculation              $calculation
     * @param TaxHelper                $taxHelper
     * @param SgLoggerInterface        $logger
     * @param Session                  $customerSession
     * @param GroupRepositoryInterface $groupRepository
     */
    public function __construct(
        CoreInterface $sgCore,
        Calculation $calculation,
        TaxHelper $taxHelper,
        SgLoggerInterface $logger,
        Session $customerSession,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->config          = $sgCore;
        $this->calculation     = $calculation;
        $this->taxHelper       = $taxHelper;
        $this->logger          = $logger;
        $this->customerSession = $customerSession;
        $this->groupRepository = $groupRepository;
    }

    /**
     * Returns whether to use tax prices
     * on product/coupon export
     *
     * @return bool
     */
    public function couponInclTax()
    {
        $priceInclTax  = $this->config->getConfigByPath(TaxConfig::CONFIG_XML_PATH_PRICE_INCLUDES_TAX)->getValue();
        $afterDiscount = $this->config->getConfigByPath(TaxConfig::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT)->getValue();

        if ($priceInclTax || !$afterDiscount) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves the default tax class per cent off
     *
     * @param \Magento\Quote\Model\Quote $quote
     *
     * @return float
     * @throws Exception\LocalizedException
     * @throws Exception\NoSuchEntityException
     */
    public function getShippingTaxPercent(\Magento\Quote\Model\Quote $quote)
    {
        $taxClassId      = $quote->getCustomerTaxClassId();
        $defaultTaxClass = $this->config->getConfigByPath(
            TaxConfig::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
            $quote->getStoreId()
        )->getValue();

        // customer group can be auto assigned based on vat id an magento's auto group assignment feature
        if ($this->customerSession->getCustomerGroupId() !== null) {
            $groupId    = $this->customerSession->getCustomerGroupId();
            $taxClassId = $this->groupRepository->getById($groupId)->getTaxClassId();
        }

        $taxClasses      = $this->calculation->getTaxRates(
            $quote->getBillingAddress()->getData(),
            $quote->getShippingAddress()->getData(),
            $taxClassId
        );

        if (!isset($taxClasses[$defaultTaxClass]) || empty($quote->getShippingAddress()->getAppliedTaxes())) {
            $this->logger->debug('Default class: ' . $defaultTaxClass);
            $this->logger->debug('Returned only these tax classes: ' . print_r($taxClasses, true));

            return 0.0;
        }

        return number_format($taxClasses[$defaultTaxClass], 2);
    }
}

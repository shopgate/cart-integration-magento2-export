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

use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote as MageQuote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data as MageTaxHelper;
use Shopgate\Base\Helper\Product\Type;
use Shopgate\Base\Helper\Product\Utility;
use Shopgate\Base\Helper\Quote\Coupon;
use Shopgate\Base\Helper\Quote\Customer;
use Shopgate\Base\Helper\Shopgate\CartItem;
use Shopgate\Base\Helper\Shopgate\ExternalCoupon;
use Shopgate\Base\Model\Rule\Condition\ShopgateOrder as OrderCondition;
use Shopgate\Base\Model\Shopgate\Extended\Base;
use Shopgate\Base\Model\Utility\Registry;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Helper\Tax as TaxHelper;

class Quote extends \Shopgate\Base\Helper\Quote
{
    /** Code and name for a coupon, which just represents cart rules */
    const CART_RULE_COUPON_CODE = '1';
    const CART_RULE_COUPON_NAME = 'Discount';

    /** @var CartItem */
    private $cartItemHelper;
    /** @var ExternalCoupon */
    private $couponHelper;
    /** @var TaxHelper */
    private $taxHelper;
    /** @var ShippingMethodConverter */
    private $shippingConverter;

    /**
     * @param Type                    $type
     * @param CartItem                $cartItemHelper
     * @param ExternalCoupon          $externalCoupon
     * @param MageQuote               $quote
     * @param Base                    $base
     * @param SgLoggerInterface       $logger
     * @param Utility                 $productHelper
     * @param MageTaxHelper           $taxData
     * @param Customer                $quoteCustomer
     * @param Registry                $coreRegistry
     * @param StoreManagerInterface   $storeManager
     * @param TaxHelper               $taxHelper
     * @param Coupon                  $couponQuoteHelper
     * @param QuoteRepository         $quoteRepository
     * @param ShippingMethodConverter $shippingConverter
     */
    public function __construct(
        Type $type,
        CartItem $cartItemHelper,
        ExternalCoupon $externalCoupon,
        MageQuote $quote,
        Base $base,
        SgLoggerInterface $logger,
        Utility $productHelper,
        MageTaxHelper $taxData,
        Customer $quoteCustomer,
        Registry $coreRegistry,
        StoreManagerInterface $storeManager,
        TaxHelper $taxHelper,
        Coupon $couponQuoteHelper,
        QuoteRepository $quoteRepository,
        ShippingMethodConverter $shippingConverter
    ) {
        $this->cartItemHelper    = $cartItemHelper;
        $this->couponHelper      = $externalCoupon;
        $this->taxHelper         = $taxHelper;
        $this->shippingConverter = $shippingConverter;

        parent::__construct(
            $quote,
            $base,
            $logger,
            $productHelper,
            $taxData,
            $quoteCustomer,
            $coreRegistry,
            $storeManager,
            $couponQuoteHelper,
            $quoteRepository,
            $type
        );
    }

    /**
     * @return \ShopgateExternalCoupon[]
     */
    public function getValidatedCoupons()
    {
        $coupons = [];
        /** @var bool $invalidateCRP return an invalidated Cart Rule Coupon only if it was actually requested */
        $invalidateCRP     = false;
        $discountAmount    = $this->quote->getSubtotal() - $this->quote->getSubtotalWithDiscount();
        $couponsIncludeTax = $this->taxHelper->couponInclTax();
        $quoteCurrency     = $this->quote->getStoreCurrencyCode();
        foreach ($this->sgBase->getExternalCoupons() as $coupon) {
            if ($coupon->getCode() === self::CART_RULE_COUPON_CODE) {
                $invalidateCRP = true;
                continue;
            }
            if (!$coupon->getNotValidMessage()) {
                $coupon->setIsValid(true);
                $coupon->setCurrency($quoteCurrency);
                $coupon->setIsFreeShipping((bool)$this->quote->getShippingAddress()->getFreeShipping());
                $couponsIncludeTax ? $coupon->setAmountGross($discountAmount) : $coupon->setAmountNet($discountAmount);
            }
            $coupons[] = $this->couponHelper->dataToEntity($coupon->toArray());
        }
        if (empty($coupons) && !empty($discountAmount)) {
            $couponData    = [
                'code'             => self::CART_RULE_COUPON_CODE,
                'name'             => __(self::CART_RULE_COUPON_NAME),
                'is_valid'         => true,
                'currency'         => $quoteCurrency,
                'is_free_shipping' => (bool)$this->quote->getShippingAddress()->getFreeShipping(),
                'amount_gross'     => $couponsIncludeTax ? $discountAmount : null,
                'amount_net'       => $couponsIncludeTax ? null : $discountAmount,

            ];
            $coupons[]     = $this->couponHelper->dataToEntity($couponData);
            $invalidateCRP = false;
        }
        if ($invalidateCRP) {
            $couponData = [
                'code'             => self::CART_RULE_COUPON_CODE,
                'name'             => __(self::CART_RULE_COUPON_NAME),
                'is_valid'         => false,
                'currency'         => $quoteCurrency,
                'is_free_shipping' => false,
                'amount_gross'     => 0,
                'amount_net'       => 0,

            ];
            $coupons[]  = $this->couponHelper->dataToEntity($couponData);
        }

        return $coupons;
    }

    /**
     * @return \ShopgateCartItem[]
     * @throws \Exception
     */
    public function getValidatedItems()
    {
        //todo-sg check validation for budnle products in check_cart and check_stock
        $products = [];
        $this->quote->collectTotals();

        foreach ($this->quote->getAllVisibleItems() as $item) {
            $price   = $item->getProduct()->getFinalPrice();
            $percent = $item->getTaxPercent();

            if ($this->taxData->priceIncludesTax($this->quote->getStore())) {
                $priceInclTax = $price;
                $priceExclTax = $price / (1 + ($percent / 100));
            } else {
                $priceInclTax = $price * (1 + ($percent / 100));
                $priceExclTax = $price;
            }
            $type                         = $this->typeHelper->getType($item);
            $stockData                    = $type->getStockData();
            $productId                    = $type->getItemId();
            $data['unit_amount']          = round($priceExclTax, 4);
            $data['unit_amount_with_tax'] = round($priceInclTax, 4);

            $product = $this->sgBase->getItemById($productId);
            if ($product) {
                $product->setUnhandledError(0);
                $data['item_number'] = $product->getItemNumber();
                $data['options']     = $product->getOptions();
                $data['inputs']      = $product->getInputs();
                $data['attributes']  = $product->getAttributes();
            }

            $products[] = $this->cartItemHelper->dataToEntity(array_merge($data, $stockData));
        }

        /**
         * Export invalidated items
         */
        foreach ($this->sgBase->getItemsWithUnhandledErrors() as $item) {
            $data     = $item->toArray();
            $cartItem = $this->cartItemHelper->dataToEntity($data);
            $cartItem->setError($item->getErrorCode());
            $cartItem->setErrorText($item->getErrorText());
            $products[] = $cartItem;
        }

        return $products;
    }

    /**
     * Get all valid shipping methods
     *
     * @see \Magento\Tax\Model\Config::needPriceConversion() - must return true for price with tax to export
     *
     * @return \ShopgateShippingMethod[]
     */
    public function getValidatedShippingMethods()
    {
        $methods = [];
        $this->quote->setData('totals_collected_flag', false);
        $this->quoteRepository->save($this->quote);
        $taxPercent = $this->taxHelper->getShippingTaxPercent($this->quote);
        $address    = $this->quote->getShippingAddress();
        $rates      = $address->setCollectShippingRates(true)->collectShippingRates()->getAllShippingRates();

        /** @var \Magento\Quote\Model\Quote\Address\Rate $rate */
        foreach ($rates as $key => $rate) {
            $shipMethod = $this->shippingConverter->modelToDataObject($rate, $this->quote->getQuoteCurrencyCode());
            if ($shipMethod->getErrorMessage() !== false) {
                $this->log->debug('Shipping method error: ' . $shipMethod->getErrorMessage());
                continue;
            }
            $sgMethod = new \ShopgateShippingMethod();
            $sgMethod->setId($rate->getCode());
            $sgMethod->setShippingGroup(strtoupper($rate->getCarrier()));
            $sgMethod->setSortOrder($key);
            $sgMethod->setTitle($shipMethod->getCarrierTitle() . ': ' . $shipMethod->getMethodTitle());
            $sgMethod->setDescription($rate->getMethodDescription() ?: '');
            $sgMethod->setAmount($shipMethod->getPriceExclTax());
            $sgMethod->setAmountWithTax($shipMethod->getPriceInclTax());
            $sgMethod->setTaxClass($this->quote->getCustomerTaxClassId());
            $sgMethod->setTaxPercent($taxPercent);

            $methods[] = $sgMethod;
        }

        return $methods;
    }

    /**
     * Set shipping method chosen by the customer
     * in case we need to calculate shipping based
     * discounts or free shipping
     */
    protected function setShipping()
    {
        $info   = $this->sgBase->getShippingInfos();
        $method = ($info instanceof \ShopgateShippingInfo && $info->getName()) ? $info->getName() : 'shopgate_fix';
        $this->quote->getShippingAddress()
                    ->setShippingMethod($method)
                    ->setData(OrderCondition::CLIENT_ATTRIBUTE, $this->sgBase->getClient()->getType());
    }
}

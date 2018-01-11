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

namespace Shopgate\Export\Tests\Integration\Helper;

use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Base\Tests\Integration\Db\ConfigManager;

class TaxTest extends \PHPUnit\Framework\TestCase
{
    /** @var ConfigManager */
    protected $cfgManager;
    /** @var \Shopgate\Export\Helper\Tax */
    protected $class;
    /** @var \Magento\Quote\Model\QuoteFactory */
    protected $quoteFactory;
    /** @var AddressFactory */
    protected $addressFactory;

    /**
     * Load object manager for initialization
     */
    public function setUp()
    {
        $objectManager        = Bootstrap::getObjectManager();
        $this->cfgManager     = new ConfigManager;
        $this->class          = $objectManager->create('Shopgate\Export\Helper\Tax');
        $this->quoteFactory   = $objectManager->create('Magento\Quote\Model\QuoteFactory');
        $this->addressFactory = $objectManager->create('Magento\Quote\Model\Quote\AddressFactory');
    }

    /**
     * Gets Miami's tax percentage
     */
    public function testGetShippingTaxPercent()
    {
        $storeId = 1;
        $quote   = $this->quoteFactory->create();
        $quote->setStoreId($storeId);
        //tax based on shipping address
        $this->cfgManager->setConfigValue(
            TaxConfig::CONFIG_XML_PATH_BASED_ON,
            'shipping',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        //use product tax class
        $this->cfgManager->setConfigValue(
            TaxConfig::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
            2,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        //Miami region
        $addressData = [
            'country_id' => 'US',
            'region_id'  => 33,
            'postcode'   => '12345'
        ];

        $address = $this->addressFactory->create();
        $address->addData($addressData);
        $quote->setBillingAddress($address);
        $quote->setShippingAddress($address);

        $rate = $this->class->getShippingTaxPercent($quote);
        $this->assertEquals('8.25', $rate);
    }

    /**
     * Cleanup created table data
     */
    public function tearDown()
    {
        $this->cfgManager->removeConfigs();
    }
}

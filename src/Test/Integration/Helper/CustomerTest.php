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

namespace Shopgate\Export\Test\Integration\Helper;

use Exception;
use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Config\ScopePool;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Base\Tests\Integration\Db\ConfigManager;
use Shopgate\Export\Model\Service\Export;
use ShopgateLibraryException;

/**
 * @coversDefaultClass \Shopgate\Export\Helper\Customer
 */
class CustomerTest extends TestCase
{
    /** @var CustomerFactory */
    protected $customerFactory;
    /** @var ConfigManager */
    protected $cfgManager;
    /** @var Customer[] */
    protected $customers;
    /** @var  ScopePool */
    protected $scopePool;
    /** @var Export */
    private $class;

    /**
     * Load object manager for initialization
     */
    public function setUp(): void
    {
        $objectManager         = Bootstrap::getObjectManager();
        $this->cfgManager      = new ConfigManager;
        $this->class           = $objectManager->create('Shopgate\Export\Model\Service\Export');
        $this->customerFactory = $objectManager->create('Magento\Customer\Model\CustomerFactory');
        $this->scopePool       = $objectManager->create('Magento\Framework\App\Config\ScopePool');
    }

    /**
     * Test that we can set a password on a user
     * and authenticate
     *
     * @covers ::getCustomer
     */
    public function testGetAuthenticatedCustomer()
    {
        $customer = $this->createCustomer();
        $customer->setData('confirmation', false);
        $customer->save();
        $sgCustomer = $this->class->getCustomerRaw($customer->getEmail(), $customer->getPassword());
        $result     = is_object($sgCustomer) && $sgCustomer->getMail() === $customer->getEmail();

        $this->assertTrue($result);
    }

    /**
     * @covers ::getCustomer
     */
    public function testNonAuthenticatedCustomer()
    {
        $exceptionCode = ShopgateLibraryException::PLUGIN_WRONG_USERNAME_OR_PASSWORD;
        $this->setExpectedException(
            'ShopgateLibraryException',
            ShopgateLibraryException::getMessageFor($exceptionCode),
            $exceptionCode
        );
        $customer = $this->createCustomer();

        $this->class->getCustomerRaw($customer->getEmail(), 'wrong_password');
    }

    /**
     * Test if customer throws a non confirmed exception. This test may be
     * incomplete as we are having issues with proper codePool setup.
     *
     * @covers ::getCustomer
     */
    public function testConfirmRequiredCustomer()
    {
        $this->markTestSkipped('Skipping test as we are having issues with code pool');

        $exceptionCode = ShopgateLibraryException::PLUGIN_CUSTOMER_ACCOUNT_NOT_CONFIRMED;
        $this->setExpectedException(
            'ShopgateLibraryException',
            ShopgateLibraryException::getMessageFor($exceptionCode),
            $exceptionCode
        );
        /** @var Customer $customer */
        $customer = $this->createCustomer();
        $customer->setData('confirmation', '12345');
        $customer->save();
        $this->scopePool->getScope('websites')->setValue(AccountManagement::XML_PATH_IS_CONFIRM, 1);
        $this->class->getCustomerRaw($customer->getEmail(), $customer->getPassword());
    }

    /**
     * Reset the customer in the database
     */
    public function tearDown(): void
    {
        /**
         * @var Registry $registry
         */
        $registry = Bootstrap::getObjectManager()->get('\Magento\Framework\Registry');
        $registry->register('isSecureArea', true, true);

        foreach ($this->customers as $customer) {
            $customer->delete();
        }
    }

    /**
     * Helps create a magento customer
     *
     * @param null|string $user
     * @param null|string $pass
     *
     * @return Customer
     * @throws Exception
     */
    private function createCustomer($user = null, $pass = null)
    {
        if (!$pass) {
            $pass = 'test' . rand(0, 99999);
        }

        if (!$user) {
            $user = $pass . '@shopgate.com';
        }

        /** @var Customer $customer */
        $customer = $this->customerFactory->create();
        $customer->setData('email', $user);
        $customer->setData('firstname', 'Test');
        $customer->setData('lastname', 'Tester');
        $customer->setPassword($pass);
        $customer->save();

        return $this->customers[] = $customer;
    }
}

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

namespace Shopgate\Export\Test\Integration\Model\Export;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Export\Model\Export\Product as SubjectUnderTest;
use Shopgate_Model_Catalog_TierPrice;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/product_with_tier_pricing.php
 */
class ProductTest extends TestCase
{
    /** @var SubjectUnderTest */
    protected $subjectUnderTest;
    /** * @var \Magento\Framework\App\ObjectManager */
    protected $objectManager;
    /** @var ProductRepositoryInterface */
    private $productRepository;

    /**
     * Setup
     */
    public function setUp()
    {
        $this->objectManager     = ObjectManager::getInstance();
        $this->subjectUnderTest  = $this->objectManager->create(SubjectUnderTest::class);
        $this->productRepository = $this->objectManager->create(ProductRepositoryInterface::class);
    }

    /**
     * Checking both fixed and percentage tier prices
     * Check fixture for values, they go like this:
     * 1) 1000-8$
     * 2) 1000-20$
     * 3) 1000-50%
     *
     * @throws Exception
     */
    public function testNonRuleTierPrices(): void
    {
        $product = $this->productRepository->get('simple-test-product');
        $this->subjectUnderTest->setItem($product)->setPrice();
        $export = $this->subjectUnderTest->getPrice()->getTierPricesGroup();

        /** @var Shopgate_Model_Catalog_TierPrice $group */
        foreach ($export as $group) {
            $this->assertContains($group->getReduction(), ['992', '980', '500']);
        }
    }

    /**
     * @throws Exception
     */
    public function testGroupUids(): void
    {
        $product = $this->productRepository->get('simple-test-product');
        $this->subjectUnderTest->setItem($product)->setPrice();
        $export = $this->subjectUnderTest->getPrice()->getTierPricesGroup();

        $this->assertEquals($export[1]->getCustomerGroupUid(), (string) GroupManagement::NOT_LOGGED_IN_ID);
        $this->assertEquals($export[2]->getCustomerGroupUid(), '1');
    }

    /**
     * @param string $expectedType
     * @param int    $sku
     *
     * @dataProvider orderInfoProvider
     * @throws Exception
     */
    public function testOrderInfoJson($expectedType, $sku): void
    {
        $product = $this->productRepository->get($sku);
        $this->subjectUnderTest->setItem($product)->setInternalOrderInfo();
        $orderInfo = $this->subjectUnderTest->getInternalOrderInfo();
        $this->assertJson($orderInfo);
        /** @noinspection PhpComposerExtensionStubsInspection */
        $info = json_decode($orderInfo, true);
        $this->assertEquals($expectedType, $info['item_type']);
    }

    /**
     * @throws Exception
     */
    public function testSetWeight(): void
    {
        $product = $this->productRepository->get('simple-test-product');
        $this->subjectUnderTest->setItem($product)->setWeight();
        $this->assertEquals(15.5, $this->subjectUnderTest->getWeight());
    }

    /**
     * @covers ::setAttributeGroups
     * @throws NoSuchEntityException
     */
    public function testNotEmptyAttributeGroups(): void
    {
        $product = $this->productRepository->get('MH01');
        $this->subjectUnderTest->setItem($product)->setAttributeGroups();
        $this->assertNotEmpty($this->subjectUnderTest->getAttributeGroups());
    }

    /**
     * Test internal_order_info for products
     *
     * @return array
     */
    public function orderInfoProvider(): array
    {
        return [
            'simple product order for simple'       => ['simple', '24-MB01'],
            'config product order for configurable' => ['configurable', 'MH01'],
            'grouped product order for grouped'     => ['grouped', '24-WG085_Group'],
            'grouped product order for bundled '    => ['bundle', '24-WG080']
        ];
    }
}

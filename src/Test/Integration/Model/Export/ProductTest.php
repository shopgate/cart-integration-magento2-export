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
use Magento\Catalog\Api\Data\ProductTierPriceInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\GroupManagement;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Export\Model\Export\Product as SubjectUnderTest;
use Shopgate_Model_Catalog_TierPrice;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 * @magentoAppArea      frontend
 */
class ProductTest extends TestCase
{
    /** @var SubjectUnderTest */
    protected $subjectUnderTest;
    /** @var ProductFactory */
    protected $productFactory;
    /** * @var \Magento\Framework\App\ObjectManager */
    protected $objectManager;

    /**
     * Setup
     */
    public function setUp()
    {
        $this->objectManager    = ObjectManager::getInstance();
        $this->subjectUnderTest = $this->objectManager->create(SubjectUnderTest::class);
        $this->productFactory   = $this->objectManager->create(ProductFactory::class);
    }

    /**
     * @param int   $expected
     * @param float $salePrice
     * @param array $tierPrices
     *
     * @dataProvider tierPriceReductionProvider
     * @throws Exception
     */
    public function testNonRuleTierPrices($expected, $salePrice, $tierPrices): void
    {
        $product = $this->createProduct()->setFinalPrice($salePrice);
        $this->addTierPrices($product, $tierPrices);
        $this->subjectUnderTest->setItem($product)->setPrice();
        $export = $this->subjectUnderTest->getPrice()->getTierPricesGroup();

        /** @var Shopgate_Model_Catalog_TierPrice $group */
        $group = array_pop($export);
        $this->assertEquals($expected, $group->getReduction());
    }

    /**
     * @param $expected
     * @param $tierPrices
     *
     * @dataProvider tierPriceUidProvider
     * @throws Exception
     */
    public function testGroupUids($expected, $tierPrices): void
    {
        $product = $this->createProduct();
        $this->addTierPrices($product, $tierPrices);
        $this->subjectUnderTest->setItem($product)->setPrice();
        $export = $this->subjectUnderTest->getPrice()->getTierPricesGroup();

        /** @var Shopgate_Model_Catalog_TierPrice $group */
        $group = array_pop($export);
        $this->assertEquals($expected, $group->getCustomerGroupUid());
    }

    /**
     * @param string $expectedJson
     * @param int    $productId
     *
     * @dataProvider orderInfoProvider
     * @throws Exception
     */
    public function testOrderInfoJson($expectedJson, $productId): void
    {
        $product = $this->createProduct()->load($productId);
        $this->subjectUnderTest->setItem($product)->setInternalOrderInfo();
        $orderInfo = $this->subjectUnderTest->getInternalOrderInfo();
        $this->assertJson($orderInfo);
        $this->assertEquals($expectedJson, $orderInfo);
    }

    /**
     * @throws Exception
     */
    public function testSetWeight(): void
    {
        $product = $this->createProduct();
        $this->subjectUnderTest->setItem($product)->setWeight();
        $this->assertEquals($product->getData('weight'), $this->subjectUnderTest->getWeight());
    }

    /**
     * @covers ::setAttributeGroups
     */
    public function testNotEmptyAttributeGroups(): void
    {
        $product = $this->createProduct()->load(83);
        $this->subjectUnderTest->setItem($product)->setAttributeGroups();
        $this->assertNotEmpty($this->subjectUnderTest->getAttributeGroups());
    }

    /**
     * Tests reductions
     *
     * @return array
     */
    public function tierPriceReductionProvider(): array
    {
        return [
            '5 off'    => [
                'expected'    => 5,
                'final price' => 20,
                'tier prices' => [
                    [
                        'group_id' => '1',
                        'qty'      => '4',
                        'value'    => '15',
                    ]
                ]
            ],
            '100% off' => [
                'expected'    => 0,
                'final price' => 20,
                'tier prices' => [
                    [
                        'group_id' => '1',
                        'qty'      => '4',
                        'value'    => '20',
                    ]
                ]
            ]
        ];
    }

    /**
     * Tests Group UIDs
     *
     * @return array
     */
    public function tierPriceUidProvider(): array
    {
        return [
            'group 1'    => [
                'expected'    => 1,
                'tier prices' => [
                    [
                        'group_id' => '1',
                        'qty'      => 0,
                        'value'    => 0,
                    ]
                ]
            ],
            'all groups' => [
                'expected'    => null,
                'tier prices' => [
                    [
                        'group_id' => GroupManagement::CUST_GROUP_ALL,
                        'qty'      => 0,
                        'value'    => 0,
                    ]
                ]
            ]
        ];
    }

    /**
     * Test internal_order_info for products
     *
     * @return array
     */
    public function orderInfoProvider()
    {
        return [
            'simple product order info #1'     => ['{"store_view_id":"1","product_id":"1","item_type":"simple"}', 1],
            'simple product order info #2'     => ['{"store_view_id":"1","product_id":"2","item_type":"simple"}', 2],
            'config product order info #67'    => [
                '{"store_view_id":"1","product_id":"67","item_type":"configurable"}',
                67
            ],
            'config product order info #83'    => [
                '{"store_view_id":"1","product_id":"83","item_type":"configurable"}',
                83
            ],
            'grouped product order info #2046' => [
                '{"store_view_id":"1","product_id":"2046","item_type":"grouped"}',
                2046
            ]
        ];
    }

    /**
     * Creates a simple product
     *
     * @return Product
     * @throws Exception
     */
    private function createProduct()
    {
        /** @var Product $categoryFactory */
        $product = $this->productFactory->create();
        $product->setName('Test Product')
                ->setTypeId('simple')
                ->setAttributeSetId(4)
                ->setSku('test-SKU')
                ->setWebsiteIds([(1)])
                ->setVisibility(4)
                ->setPrice(50)
                ->setWeight(23.5)
                ->setData('image', '/testimg/test.jpg')
                ->setData('small_image', '/testimg/test.jpg')
                ->setData('thumbnail', '/testimg/test.jpg')
                ->setData(
                    'stock_data',
                    [
                        'use_config_manage_stock' => 0,
                        'manage_stock'            => 1,
                        'min_sale_qty'            => 1,
                        'max_sale_qty'            => 2,
                        'is_in_stock'             => 1,
                        'qty'                     => 100
                    ]
                );

        return $product;
    }

    /**
     * Helps add tier prices to the product
     *
     * @param Product $product
     * @param array   $tierPrices
     */
    private function addTierPrices(Product $product, array $tierPrices): void
    {
        $nodes = [];
        foreach ($tierPrices as $tierPrice) {
            /** @var ProductTierPriceInterface $tierPriceNode */
            $tierPriceNode = $this->objectManager->create(ProductTierPriceInterface::class);
            $nodes[]       = $tierPriceNode
                ->setCustomerGroupId($tierPrice['group_id'])
                ->setQty($tierPrice['qty'])
                ->setValue($tierPrice['value']);
        }

        $product->setTierPrices($nodes);
    }
}

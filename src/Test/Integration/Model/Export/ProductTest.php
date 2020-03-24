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

declare(strict_types=1);

namespace Shopgate\Export\Test\Integration\Model\Export;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Model\Service\Config\Core;
use Shopgate\Base\Model\Utility\SgLogger;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Base\Tests\Integration\Db\ConfigManager;
use Shopgate\Export\Api\ExportInterface;
use Shopgate\Export\Model\Config\Source\ChildDescription;
use Shopgate\Export\Model\Config\Source\Description;
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
    /** @var ObjectManagerInterface */
    protected $objectManager;
    /** @var ProductRepositoryInterface */
    private $productRepository;

    /**
     * Setup
     */
    public function setUp()
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $core                = $this->objectManager->get(Core::class);
        $logger              = $this->objectManager->get(SgLogger::class);
        $this->objectManager->addSharedInstance($core, CoreInterface::class);
        $this->objectManager->addSharedInstance($logger, SgLoggerInterface::class);
        $this->subjectUnderTest  = $this->objectManager->get(SubjectUnderTest::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * Checking both fixed and percentage tier prices
     * Check fixture for values, they go like this:
     * 1) 1000-8$
     * 2) 1000-20$
     * 3) 1000-50%
     *
     * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/product_with_tier_pricing.php
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
     * Basic description tests
     *
     * @param string $sku
     * @param string $expected
     * @param array  $configs
     *
     * @throws NoSuchEntityException
     * @throws Exception
     * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/product_with_description.php
     * @dataProvider        descriptionProvider
     */
    public function testSetDescription(string $sku, string $expected, array $configs): void
    {
        /** @var ConfigManager $configManager */
        $configManager = $this->objectManager->get(ConfigManager::class);
        foreach ($configs as $path => $value) {
            $configManager->setConfigValue($path, $value);
        }
        $product = $this->productRepository->get($sku);
        $this->subjectUnderTest->setItem($product)->setDescription();
        $description = $this->subjectUnderTest->getDescription();
        $this->assertSame($expected, $description);
    }

    /**
     * @return array
     */
    public function descriptionProvider(): array
    {
        return [
            '> long description by default'         => [
                'sku'                  => 'simple-test-product-two',
                'expected description' => 'Long Description1',
                'configs'              => []
            ],
            '> both, long description first'        => [
                'sku'                  => 'simple-test-product-two',
                'expected description' => 'Long Description1<br /><br />Short Description2',
                'configs'              => [
                    ExportInterface::PATH_PROD_DESCRIPTION => Description::ID_DESCRIPTION_AND_SHORT_DESCRIPTION
                ]
            ],
            '> both, short description first'       => [
                'sku'                  => 'simple-test-product-two',
                'expected description' => 'Short Description2<br /><br />Long Description1',
                'configs'              => [
                    ExportInterface::PATH_PROD_DESCRIPTION => Description::ID_SHORT_DESCRIPTION_AND_DESCRIPTION
                ]
            ],
            '> short description only'              => [
                'sku'                  => 'simple-test-product-two',
                'expected description' => 'Short Description2',
                'configs'              => [
                    ExportInterface::PATH_PROD_DESCRIPTION => Description::ID_SHORT_DESCRIPTION
                ]
            ],
            '> configurable child description only' => [
                'sku'                  => 'MH01-XS-Black',
                'expected description' => '<p>Ideal for cold-weather training or work outdoors, the Chaz Hoodie' .
                    ' promises superior warmth with every wear. Thick material blocks out the wind as ribbed' .
                    ' cuffs and bottom band seal in body heat.</p>
<p>&bull; Two-tone gray heather hoodie.<br />&bull; Drawstring-adjustable hood. <br />&bull; Machine wash/dry.</p>',
                'configs'              => [
                    ExportInterface::PATH_PROD_CHILD_DESCRIPTION => ChildDescription::ID_CHILD_ONLY
                ]
            ]
        ];
    }

    /**
     * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/product_with_tier_pricing.php
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testNoDescription(): void
    {
        $product = $this->productRepository->get('simple-test-product');
        $this->subjectUnderTest->setItem($product)->setDescription();
        $description = $this->subjectUnderTest->getDescription();
        $this->assertSame('', $description);
    }

    /**
     * Tests using parent + child product descriptions
     *
     * @param string $sku
     * @param string $expected
     * @param array  $configs
     *
     * @throws NoSuchEntityException
     * @throws Exception
     * @dataProvider        parentProvider
     * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/add_description_to_configurable.php
     */
    public function testSetParentDescription(string $sku, string $expected, array $configs): void
    {
        /** @var ConfigManager $configManager */
        $configManager = $this->objectManager->get(ConfigManager::class);
        foreach ($configs as $path => $value) {
            $configManager->setConfigValue($path, $value);
        }
        $parent  = $this->productRepository->get('MH01');
        $product = $this->productRepository->get($sku);
        /** @noinspection PhpParamsInspection */
        $this->subjectUnderTest->setParentItem($parent);
        $this->subjectUnderTest->setItem($product)->setDescription();
        $description = $this->subjectUnderTest->getDescription();
        $this->assertSame($expected, $description);
    }

    /**
     * @return array
     */
    public function parentProvider(): array
    {
        $parentDescription = 'Parent Long';
        $childDescription  = '<p>Ideal for cold-weather training or work outdoors, the Chaz Hoodie' .
            ' promises superior warmth with every wear. Thick material blocks out the wind as ribbed' .
            ' cuffs and bottom band seal in body heat.</p>
<p>&bull; Two-tone gray heather hoodie.<br />&bull; Drawstring-adjustable hood. <br />&bull; Machine wash/dry.</p>';

        return [
            '> configurable, both, parent first'                    => [
                'sku'                  => 'MH01-XS-Black',
                'expected description' => $parentDescription . $childDescription,
                'configs'              => [
                    ExportInterface::PATH_PROD_CHILD_DESCRIPTION => ChildDescription::ID_BOTH_PARENT_FIRST
                ]
            ],
            '> configurable, both, child first'                     => [
                'sku'                  => 'MH01-XS-Black',
                'expected description' => $childDescription . $parentDescription,
                'configs'              => [
                    ExportInterface::PATH_PROD_CHILD_DESCRIPTION => ChildDescription::ID_BOTH_CHILD_FIRST
                ]
            ],
            '> configurable, parent description only'               => [
                'sku'                  => 'MH01-XS-Black',
                'expected description' => $parentDescription,
                'configs'              => [
                    ExportInterface::PATH_PROD_CHILD_DESCRIPTION => ChildDescription::ID_PARENT_ONLY
                ]
            ],
            '> configurable, both, child first + short description' => [
                'sku'                  => 'MH01-XS-Black',
                'expected description' => "{$childDescription}<br /><br />{$parentDescription}<br /><br />Parent Short",
                'configs'              => [
                    ExportInterface::PATH_PROD_CHILD_DESCRIPTION => ChildDescription::ID_BOTH_CHILD_FIRST,
                    ExportInterface::PATH_PROD_DESCRIPTION       => Description::ID_DESCRIPTION_AND_SHORT_DESCRIPTION
                ]
            ]
        ];
    }

    /**
     * @throws Exception
     * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/product_with_tier_pricing.php
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
     * @magentoDataFixture  ../../../../vendor/shopgate/cart-integration-magento2-export/src/Test/Integration/fixtures/product_with_tier_pricing.php
     * @throws Exception
     */
    public function testSetWeight(): void
    {
        $product = $this->productRepository->get('simple-test-product');
        $this->subjectUnderTest->setItem($product)->setWeight();
        $this->assertEquals(15.5, $this->subjectUnderTest->getWeight());
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testNotEmptyAttributeGroups(): void
    {
        $product = $this->productRepository->get('MH01');
        $this->subjectUnderTest->setItem($product)->setAttributeGroups();
        $this->assertNotEmpty($this->subjectUnderTest->getAttributeGroups());
    }

    /**
     * @param float  $expected
     * @param string $sku
     *
     * @dataProvider inventoryDataProvider
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testInventory(float $expected, string $sku): void
    {
        $product = $this->productRepository->get($sku);
        $this->subjectUnderTest->setItem($product)->setStock();
        $stock = $this->subjectUnderTest->getStock();
        $this->assertEquals($expected, $stock->getStockQuantity());
    }

    /**
     * @return string[]
     */
    public function inventoryDataProvider(): array
    {
        return [
            ['100', '24-MB01'],
            ['0', 'MH01'], // Config
            ['0', '24-WG085_Group'], // Group
            ['0', '24-WG080'],
            ['100', '24-WB07'],
            ['100', 'MH01-XS-Black'], // Simple of config
            ['0', '24-WG080'], // Bundle
            ['100', '24-WG085'], // Simple 1 of bundle
            ['100', '24-WG081-blue'], // Simple 2 of bundle
            ['100', '243-MB04'], // Giftcard with stock
        ];
    }

    /**
     * @param string $sku
     * @param float  $qty
     * @param int    $maximumOrderQuantity
     * @param int    $minimumOrderQuantity
     * @param bool   $backOrders
     * @param bool   $isSaleable
     * @param bool   $useStock
     *
     * @dataProvider inventoryDetailDataProvider
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testInventoryDetail(
        string $sku,
        float $qty,
        int $maximumOrderQuantity,
        int $minimumOrderQuantity,
        bool $backOrders,
        bool $isSaleable,
        bool $useStock
    ): void {
        $product = $this->productRepository->get($sku);
        $this->subjectUnderTest->setItem($product)->setStock();
        $stock = $this->subjectUnderTest->getStock();
        $this->assertEquals($qty, $stock->getStockQuantity());
        $this->assertEquals($maximumOrderQuantity, $stock->getMaximumOrderQuantity());
        $this->assertEquals($minimumOrderQuantity, $stock->getMinimumOrderQuantity());
        $this->assertEquals($backOrders, $stock->getBackorders());
        $this->assertEquals($isSaleable, $stock->getIsSaleable());
        $this->assertEquals($useStock, $stock->getUseStock());
    }

    /**
     * @return string[]
     */
    public function inventoryDetailDataProvider(): array
    {
        return [
            ['24-MB01', '100', '10000', '1', '0', '1', '1'], // Simple
            ['MH01', '0', '10000', '1', '0', '1', '1'] // Config
        ];
    }

    /**
     * Test internal_order_info for products
     *
     * @return string[]
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

    /**
     * Simple test that runs against a single child product
     *
     * @throws NoSuchEntityException
     */
    public function testImages(): void
    {
        $parent  = $this->productRepository->get('MH01');
        $product = $this->productRepository->get('MH01-XS-Gray');
        /** @noinspection PhpParamsInspection */
        $this->subjectUnderTest->setParentItem($parent);
        $this->subjectUnderTest->setItem($product)->setImages();
        $images = $this->subjectUnderTest->getImages();
        $this->assertCount(3, $images);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testChildNotVisibleInCategory(): void
    {
        $parent  = $this->productRepository->get('MH01');
        $product = $this->productRepository->get('MH01-XS-Gray');
        /** @noinspection PhpParamsInspection */
        $this->subjectUnderTest->setParentItem($parent);
        $this->subjectUnderTest->setItem($product)->setCategoryPaths();
        $categoryPaths = $this->subjectUnderTest->getCategoryPaths();
        $this->assertCount(0, $categoryPaths, 'child not supposed to be in any category');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testConfigurableIsVisible(): void
    {
        $product = $this->productRepository->get('MH01');
        $this->subjectUnderTest->setItem($product)->setCategoryPaths();
        $categoryPaths = $this->subjectUnderTest->getCategoryPaths();
        $this->assertCount(4, $categoryPaths, 'configurable should be in the categories');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testSetAttributeGroups(): void
    {
        $parent = $this->productRepository->get('MH01');
        $this->subjectUnderTest->setItem($parent)->setAttributeGroups();
        $attributeGroups = $this->subjectUnderTest->getAttributeGroups();
        [$sizeGroup, $colorGroup] = $attributeGroups;
        $this->assertSame('Size', $sizeGroup->getLabel());
        $this->assertSame('Color', $colorGroup->getLabel());
    }

    /**
     * @throws NoSuchEntityException
     */
    public function testSetAttributes(): void
    {
        $parent  = $this->productRepository->get('MH01');
        $product = $this->productRepository->get('MH01-XS-Gray');
        /** @noinspection PhpParamsInspection */
        $this->subjectUnderTest->setParentItem($parent);
        $this->subjectUnderTest->setIsChild(true);
        $this->subjectUnderTest->setItem($product)->setAttributes();
        $attributes = $this->subjectUnderTest->getAttributes();
        [$sizeAttribute, $colorAttribute] = $attributes;
        $this->assertSame('XS', $sizeAttribute->getLabel());
        $this->assertSame('Gray', $colorAttribute->getLabel());
    }
}

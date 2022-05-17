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

namespace Shopgate\Export\Test\Unit\Helper\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Export\Helper\Product\Utility;

/**
 * @coversDefaultClass \Shopgate\Export\Helper\Product\Utility
 */
class UtilityTest extends TestCase
{
    /** @var ObjectManager */
    private $objectManager;
    /** @var Utility */
    private $subjectUnderTest;

    /**
     * Basic setup
     */
    protected function setUp(): void
    {
        $this->objectManager    = new ObjectManager($this);
        $this->subjectUnderTest = $this->objectManager->getObject(Utility::class);
    }

    /**
     * @param bool       $expected
     * @param int|string $magentoVisibility
     *
     * @dataProvider visibilityProvider
     */
    public function testIsVisibleInCategories(bool $expected, $magentoVisibility): void
    {
        /** @var Product $product */
        $product = $this->objectManager->getObject(Product::class);
        $product->setVisibility($magentoVisibility);
        $result = $this->subjectUnderTest->isVisibleInCategories($product);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     */
    public function visibilityProvider(): array
    {
        return [
            '> visible in cats & search'           => [
                'expected'      => true,
                'm2 visibility' => Visibility::VISIBILITY_BOTH
            ],
            '> visible in catalog only'            => [
                'expected'      => true,
                'm2 visibility' => Visibility::VISIBILITY_IN_CATALOG
            ],
            '> not visible not allowed'            => [
                'expected'      => false,
                'm2 visibility' => Visibility::VISIBILITY_NOT_VISIBLE
            ],
            '> visible in search only not allowed' => [
                'expected'      => false,
                'm2 visibility' => Visibility::VISIBILITY_IN_SEARCH
            ],
            '> visible in cats & search (string)'  => [
                'expected'      => true,
                'm2 visibility' => (string) Visibility::VISIBILITY_BOTH
            ],
            '> not visible not allowed (string)'   => [
                'expected'      => false,
                'm2 visibility' => (string) Visibility::VISIBILITY_NOT_VISIBLE
            ]
        ];
    }

    /**
     * @param int|string $visibilityString
     * @param bool       $expectedResult
     *
     * @dataProvider setVisibilityProvider
     * @covers ::setVisibility
     */
    public function testSetVisibility($visibilityString, $expectedResult): void
    {
        /** @var Product $product */
        $product = $this->objectManager->getObject(Product::class);
        $product->setVisibility($visibilityString);
        $this->assertEquals($expectedResult, $this->subjectUnderTest->setVisibility($product)->getLevel());
    }

    /**
     * Provider for testSetVisibility
     */
    public function setVisibilityProvider(): array
    {
        return [
            'visibility level for visibility_both'        => [Visibility::VISIBILITY_BOTH, 'catalog_and_search'],
            'visibility level for visibility_in_catalog'  => [Visibility::VISIBILITY_IN_CATALOG, 'catalog'],
            'visibility level for visibility_not_visible' => [Visibility::VISIBILITY_NOT_VISIBLE, 'nothing'],
            'visibility level for visibility_in_search'   => [Visibility::VISIBILITY_IN_SEARCH, 'search'],
            'visibility level not defined visibility'     => ['Test', null]
        ];
    }
}

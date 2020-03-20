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

namespace Shopgate\Export\Test\Integration\Helper\Product;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Export\Helper\Product\Utility;

/**
 * @coversDefaultClass \Shopgate\Export\Helper\Product\Utility
 */
class HelperTest extends TestCase
{
    /** @var StoreManagerInterface */
    protected $storeManager;
    /** @var ProductFactory */
    protected $productFactory;
    /** @var Utility */
    protected $class;

    /**
     * Init store manager + current class
     */
    public function setUp()
    {
        $objManager           = Bootstrap::getObjectManager();
        $this->storeManager   = $objManager->create(StoreManagerInterface::class);
        $this->class          = $objManager->create(Utility::class);
        $this->productFactory = $objManager->create(ProductFactory::class);
    }

    /**
     * @param string   $expectedLink
     * @param int|null $parentId
     * @param int      $childId
     * @param string   $visibility
     *
     * @covers ::getDeepLinkUrl
     * @covers       \Shopgate\Export\Model\Export\Product::setDeeplink
     *
     * @dataProvider getDeepLinkUrlProvider
     */
    public function testGetDeepLinkUrl($expectedLink, $parentId, $childId, $visibility): void
    {
        $product = $this->productFactory->create()->load($childId);
        $product->setVisibility($visibility);

        if ($parentId !== null) {
            $parent = $this->productFactory->create()->load($parentId);
        } else {
            $parent = null;
        }

        $calculatedLink = $this->class->getDeepLinkUrl($product, $parent);
        $this->assertEquals($expectedLink, $calculatedLink);
    }

    /**
     * @return array (expected url, parent id, child id, visibility)
     */
    public function getDeepLinkUrlProvider(): array
    {
        return [
            'parent with invisible child' => [
                'http://localhost/index.php/strive-shoulder-pack.html',
                2,
                1,
                Visibility::VISIBILITY_NOT_VISIBLE
            ],
            'parent with visible child'   => [
                'http://localhost/index.php/joust-duffle-bag.html',
                2,
                1,
                Visibility::VISIBILITY_BOTH
            ],
            'plain simple without parent' => [
                'http://localhost/index.php/joust-duffle-bag.html',
                null,
                1,
                Visibility::VISIBILITY_BOTH
            ],
        ];
    }
}

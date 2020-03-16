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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Export\Helper\Product\Retriever;
use Shopgate\Export\Model\Export\Product;

/**
 * @magentoAppArea frontend
 */
class RetrieverTest extends TestCase
{
    /** @var ProductRepositoryInterface */
    private $productRepository;
    /** @var ObjectManager */
    private $objectManager;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var Retriever */
    private $subjectUnderTest;

    /**
     * Init store manager + current class
     */
    public function setUp()
    {
        $this->objectManager     = Bootstrap::getObjectManager();
        $this->storeManager      = $this->objectManager->get(StoreManagerInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->subjectUnderTest  = $this->objectManager->get(Retriever::class);
    }

    /**
     * Check if the created product can be pulled
     * individually by UID
     *
     * @param int $uid
     *
     * @dataProvider uidProvider
     * @throws LocalizedException
     */
    public function testGetProductsByUid($uid): void
    {
        $sgProducts = $this->subjectUnderTest->getItems(null, null, [$uid]);

        foreach ($sgProducts as $sgProduct) {
            /** @var Product $sgProduct */
            $this->assertEquals($uid, $sgProduct->getUid());
        }
    }

    /**
     * Pull configurable product and the children data
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetConfigurable(): void
    {
        $this->storeManager->setCurrentStore(1);
        $configurable = $this->productRepository->get('MH01');
        $sgProducts   = $this->subjectUnderTest->getItems(null, null, [$configurable->getId()]);
        $this->assertCount(1, $sgProducts);
        /** @var Product $parent */
        $parent       = array_pop($sgProducts);
        $parentImages = $parent->getImages();
        $this->assertEquals($configurable->getId(), $parent->getUid());
        $this->assertCount(3, $parentImages);
        $parentImageOne = array_shift($parentImages);
        $this->assertContains(
            'http://localhost/pub/media/catalog/product/m/h/mh01-gray_main_1',
            $parentImageOne->getUrl()
        );

        /** @var Product[] $children */
        $children = $parent->getChildren();
        $this->assertCount(15, $children);
        $xsBlack       = array_shift($children);
        $xsBlackImages = $xsBlack->getImages();
        $this->assertCount(1, $xsBlackImages);
        $xsBlackImageOne = array_shift($xsBlackImages);
        $this->assertContains(
            'http://localhost/pub/media/catalog/product/m/h/mh01-black_main_1',
            $xsBlackImageOne->getUrl()
        );

        $xsGray       = array_shift($children);
        $xsGrayImages = $xsGray->getImages();
        $this->assertCount(3, $xsGrayImages);
        $xsGrayImageThree = array_pop($xsGrayImages);
        $this->assertContains(
            'http://localhost/pub/media/catalog/product/m/h/mh01-gray_back_1',
            $xsGrayImageThree->getUrl()
        );
    }

    /**
     * Provider for product uids
     *
     * @return array
     */
    public function uidProvider(): array
    {
        return [[21], [23]];
    }

    /**
     * @param $expected - expected number of items in collection
     * @param $limit
     * @param $offset
     *
     * @dataProvider limitProvider
     * @throws LocalizedException
     */
    public function testGetItemsLimit($expected, $limit, $offset): void
    {
        $sgProducts = $this->subjectUnderTest->getItems($limit, $offset, []);
        $this->assertCount($expected, $sgProducts);
    }

    /**
     * Provider for limits and offsets
     *
     * @return array
     */
    public function limitProvider(): array
    {
        return [
            'first 5'             => [5, 5, 0],
            'next 3'              => [3, 3, 5],
            'non-existing offset' => [0, 5, 10000]
        ];
    }
}

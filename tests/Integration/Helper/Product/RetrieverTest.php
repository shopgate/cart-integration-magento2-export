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

namespace Shopgate\Export\Tests\Integration\Helper\Product;

use Magento\Store\Model\StoreManagerInterface;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Export\Helper\Product\Retriever;

/**
 * @coversDefaultClass \Shopgate\Export\Helper\Product\Retriever
 */
class RetrieverTest extends \PHPUnit\Framework\TestCase
{
    /** @var StoreManagerInterface */
    protected $storeManager;
    /** @var Retriever */
    protected $class;

    /**
     * Init store manager + current class
     */
    public function setUp()
    {
        $objManager         = Bootstrap::getObjectManager();
        $this->storeManager = $objManager->create('Magento\Store\Model\StoreManagerInterface');
        $this->class        = $objManager->create('Shopgate\Export\Helper\Product\Retriever');
    }

    /**
     * Check if the created product can be pulled
     * individually by UID
     *
     * @param   int $uid
     *
     * @covers ::getItems
     * @dataProvider uidProvider
     */
    public function testGetProductsByUid($uid)
    {
        $sgProducts = $this->class->getItems(null, null, [$uid]);

        foreach ($sgProducts as $sgProduct) {
            /** @var \Shopgate\Export\Model\Export\Product $sgProduct */
            $this->assertEquals($uid, $sgProduct->getUid());
        }
    }

    /**
     * Provider for product uids
     *
     * @return array
     */
    public function uidProvider()
    {
        return [[21], [23]];
    }

    /**
     * @param $expected - expected number of items in collection
     * @param $limit
     * @param $offset
     *
     * @covers ::getItems
     * @dataProvider limitProvider
     */
    public function testGetItemsLimit($expected, $limit, $offset)
    {
        $sgProducts = $this->class->getItems($limit, $offset, []);
        $this->assertCount($expected, $sgProducts);
    }

    /**
     * Provider for limits and offsets
     *
     * @return array
     */
    public function limitProvider()
    {
        return [
            'first 5'             => [5, 5, 0],
            'next 3'              => [3, 3, 5],
            'non-existing offset' => [0, 5, 10000]
        ];
    }
}

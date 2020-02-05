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

namespace Shopgate\Export\Test\Unit\Helper\Cron;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Shopgate\Export\Helper\Cron\Utility as CronHelper;
use ShopgateOrderItem;

/**
 * @coversDefaultClass \Shopgate\Export\Helper\Product\Utility
 */
class UtilityTest extends TestCase
{
    /** @var ObjectManager */
    private $objectManager;
    /** @var CronHelper */
    private $cronHelper;

    /**
     * Load object manager for initialization
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        /** @var CronHelper $cronHelper */
        $this->cronHelper = $this->objectManager->getObject(CronHelper::class);
    }

    /**
     * @param int  $getQtyShipped
     * @param bool $expectedResult
     *
     * @dataProvider shippedItemsProvider
     */
    public function testHasShippedItems($getQtyShipped, $expectedResult)
    {
        /** @var Order|PHPUnit_Framework_MockObject_MockObject $orderStub */
        $orderStub = $this->getTestDouble(Order::class);
        /** @var Order\Item|PHPUnit_Framework_MockObject_MockObject $orderItemStub */
        $orderItemStub = $this->getTestDouble(Order\Item::class);

        $orderItemStub->method('getQtyShipped')
                      ->willReturn($getQtyShipped);

        $orderStub->method('getItemsCollection')
                  ->willReturn([$orderItemStub]);

        $result = $this->cronHelper->hasShippedItems($orderStub);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Tests if order item will be returned in case product id matches
     */
    public function testFindItemByProductIdSuccess()
    {
        $productId = 2;

        /** @var ShopgateOrderItem|PHPUnit_Framework_MockObject_MockObject $orderItemStub */
        $orderItemStub = $this->getTestDouble(ShopgateOrderItem::class);
        $orderItemStub->method('getInternalOrderInfo')
                      ->willReturn(
                          [
                              'product_id' => $productId
                          ]
                      );

        $result = $this->cronHelper->findItemByProductId([$orderItemStub], $productId);

        $this->assertEquals($orderItemStub, $result);
    }

    /**
     * @param int $searchedProductId
     * @param int $productId
     *
     * @dataProvider findItemProvider
     */
    public function testFindItemByProductIdFails($searchedProductId, $productId)
    {
        /** @var ShopgateOrderItem|PHPUnit_Framework_MockObject_MockObject $orderItemStub */
        $orderItemStub = $this->getTestDouble(ShopgateOrderItem::class);
        $orderItemStub->method('getInternalOrderInfo')
                      ->willReturn(
                          [
                              'product_id' => $productId
                          ]
                      );

        $result = $this->cronHelper->findItemByProductId([$orderItemStub], $searchedProductId);

        $this->assertEquals(false, $result);
    }

    /**
     * @return array
     */
    public function findItemProvider()
    {
        return [
            'Should not find product by Id, because of null as product id'         => [null, 2],
            'Should not find product by Id, because of empty string as product id' => ['', 2],
            'Should not find product by Id, because of id mismatch'                => [1, 2]
        ];
    }

    /**
     * @return array
     */
    public function shippedItemsProvider()
    {
        return [
            'null items to ship' => [null, false],
            '0 items to ship'    => [0, false],
            '1 item to ship'     => [1, true]
        ];
    }

    /**
     * @param string $class
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function getTestDouble($class)
    {
        return $this->getMockBuilder($class)
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}

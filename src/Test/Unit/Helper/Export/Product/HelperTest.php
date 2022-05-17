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

namespace Shopgate\Export\Test\Unit\Helper\Export\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Shopgate\Export\Helper\Product\Utility;
use Shopgate_Model_Catalog_Relation;

/**
 * @coversDefaultClass Utility
 */
class HelperTest extends TestCase
{
    /** @var ObjectManager */
    private ObjectManager $objectManager;
    /** @var Utility */
    private Utility $helper;

    /**
     * Load object manager for initialization
     */
    public function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->helper        = $this->objectManager->getObject('Shopgate\Export\Helper\Product\Utility');
    }

    /**
     * @param $expected  - expected mapped type from shopgate_library
     * @param $inputType - type to map
     *
     * @covers ::mapInputType
     *
     * @dataProvider mapInputTypeProvider
     */
    public function testMapInputType($expected, $inputType)
    {
        $mappedType = $this->helper->mapInputType($inputType);
        $this->assertEquals($expected, $mappedType);
    }

    /**
     * @return array
     */
    public function mapInputTypeProvider()
    {
        return [
            'mapping field to text'          => ['text', 'field'],
            'mapping area to area'           => ['area', 'area'],
            'mapping select to select'       => ['select', 'select'],
            'mapping drop_down to select'    => ['select', 'drop_down'],
            'mapping radio to select'        => ['select', 'radio'],
            'mapping checkbox to select'     => ['select', 'checkbox'],
            'mapping multiple to select'     => ['select', 'multiple'],
            'mapping multi to select'        => ['select', 'multi'],
            'mapping date to date'           => ['date', 'date'],
            'mapping date_time to datetime'  => ['datetime', 'date_time'],
            'mapping time to time'           => ['time', 'time'],
            'checking undefined type to map' => [false, 'unknown']
        ];
    }

    /**
     * @param float  $expected
     * @param string $priceType
     * @param float  $optionPrice
     * @param float  $productPrice
     *
     * @covers ::getOptionValuePrice
     * @covers ::getInputValuePrice
     * @covers       \Shopgate\Export\Model\Export\Product::setInputs
     *
     * @dataProvider getOptionValuePriceProvider
     */
    public function testGetOptionValuePrice($expected, $priceType, $optionPrice, $productPrice)
    {
        $optionMock = $this->getMockBuilder(Product\Option::class)
                           ->disableOriginalConstructor()
                           ->setMethods(['getPriceType', 'getPrice'])
                           ->getMock();

        $optionMock->expects($this->once())
                   ->method('getPriceType')
                   ->will($this->returnValue($priceType));

        $optionMock->expects($this->once())
                   ->method('getPrice')
                   ->will($this->returnValue($optionPrice));

        $productMock = $this->getMockBuilder(Product::class)
                            ->disableOriginalConstructor()
                            ->setMethods(['getFinalPrice'])
                            ->getMock();

        $productMock->expects($this->any())
                    ->method('getFinalPrice')
                    ->will($this->returnValue($productPrice));

        $calculateOptionPrice = $this->helper->getOptionValuePrice($optionMock, $productMock);
        $this->assertEquals($expected, $calculateOptionPrice);
    }

    /**
     * @return array (expected value, price type, option price, product price)
     */
    public function getOptionValuePriceProvider()
    {
        return [
            'percentage based options' => [0.2, 'percent', 2.00, 10.00],
            'fixed price options'      => [2.00, 'fixed', 2.00, 10.00]
        ];
    }

    /**
     * @param array  $relatedIds
     * @param string $type
     *
     * @covers ::createRelationProducts
     * @covers       \Shopgate\Export\Model\Export\Product::setRelations
     *
     * @dataProvider createRelationProductsProvider
     */
    public function testCreateRelationProducts($relatedIds, $type)
    {
        $relationModel = $this->helper->createRelationProducts($relatedIds, $type);
        $this->assertInstanceOf(Shopgate_Model_Catalog_Relation::class, $relationModel);
        $this->assertEquals($type, $relationModel->getType());
        $this->assertEquals($relatedIds, $relationModel->getValues());
    }

    /**
     * @return array (related ids, relation type)
     */
    public function createRelationProductsProvider()
    {
        return [
            '2 upsell relations'     => [[1, 2], 'upsell'],
            'empty upsell relations' => [[], 'upsell']
        ];
    }
}

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

namespace Shopgate\Export\Test\Unit\Model\Export;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Export\Model\Config\Source\Description;

/**
 * @coversDefaultClass \Shopgate\Export\Model\Export\Product
 */
class ProductTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProductDouble()
    {
        $productDouble = $this
            ->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $productDouble;
    }

    /**
     * Load object manager for initialization
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
    }

    /**
     * @param $expectedDescription
     * @param $descConfig
     * @param $longDesc
     * @param $shortDesc
     *
     * @covers ::setDescription
     * @dataProvider descriptionProvider
     */
    public function testSetDescription($expectedDescription, $descConfig, $longDesc, $shortDesc)
    {
        $this->markTestIncomplete('Expected strings not yet returned by the stub');
        $configValueStub = $this->getMockBuilder(Config\Value::class)
                                ->disableOriginalConstructor()
                                ->getMock();

        $configValueStub->method('getValue')
                        ->will($this->returnValue($descConfig));

        $scopeConfigStub = $this->getMockBuilder(CoreInterface::class)
                                ->disableOriginalConstructor()
                                ->getMock();

        $scopeConfigStub->method('getConfigByPath')
                        ->will($this->returnValue($configValueStub));

        $productStub = $this->getProductDouble();

        $productStub->method('getDescription')
                    ->will($this->returnValue($longDesc));

        $productStub->method('getShortDescription')
                    ->will($this->returnValue($shortDesc));

        /** @var \Shopgate\Export\Model\Export\Product $exportModel */
        $exportModel = $this->objectManager->getObject(
            'Shopgate\Export\Model\Export\Product',
            [
                'scopeConfig' => $scopeConfigStub
            ]
        );

        $exportModel->setItem($productStub)->setDescription();

        $this->assertEquals($expectedDescription, $exportModel->getDescription());
    }

    /**
     * @param string $expectedDisplayType
     * @param string $productType
     *
     * @covers ::setDisplayType
     * @dataProvider displayTypeProvider
     */
    public function testdisplayType($expectedDisplayType, $productType)
    {
        $productStub = $this->getProductDouble();

        $productStub->method('getTypeId')
                    ->will($this->returnValue($productType));

        /** @var \Shopgate\Export\Model\Export\Product $exportModel */
        $exportModel = $this->objectManager->getObject(
            'Shopgate\Export\Model\Export\Product'
        );

        $exportModel->setItem($productStub)->setDisplayType();

        $this->assertEquals($expectedDisplayType, $exportModel->getDisplayType());
    }

    /**
     * @param $productType
     *
     * @covers ::setAttributeGroups
     * @dataProvider productTypeProvider
     */
    public function testEmptyAttributeGroups($productType)
    {
        $productStub = $this->getProductDouble();

        $productStub->method('getTypeId')
                    ->will($this->returnValue($productType));

        /** @var \Shopgate\Export\Model\Export\Product $exportModel */
        $exportModel = $this->objectManager->getObject(
            'Shopgate\Export\Model\Export\Product'
        );

        $exportModel->setItem($productStub)->setAttributeGroups();

        $this->assertEmpty($exportModel->getAttributeGroups());
    }

    /**
     * @param array     $imageData
     * @param string    $smallImagePath
     *
     * @covers ::setImages
     * @dataProvider imageProvider
     */
    public function testImageSortOrder($imageData, $smallImagePath)
    {
        $productStub = $this->getProductDouble();
        $productStub->method('getMediaGalleryImages')
            ->will($this->returnValue($this->getTestCollection($imageData)));

        $productStub->method('getData')
            ->will($this->returnValueMap([['small_image', null, $smallImagePath]]));

        /** @var \Shopgate\Export\Model\Export\Product $exportModel */
        $exportModel = $this->objectManager->getObject(
            'Shopgate\Export\Model\Export\Product'
        );

        $exportModel->setItem($productStub)->setImages();

        $images = array_reverse($exportModel->getImages());
        $firstImage = array_pop($images);

        $this->assertEquals($smallImagePath, $firstImage->getUrl());
        $this->assertEquals(1, $firstImage->getIsCover());
    }

    /**
     * @return array
     */
    public function imageProvider()
    {
        return [
            'First product set as small image' => [[$this->createImageObject('1.jpg', '1'), $this->createImageObject('2.jpg', '3')], '1.jpg'],
            'Second product set as small image' => [[$this->createImageObject('1.jpg', '1'), $this->createImageObject('2.jpg', '14')], '2.jpg']
        ];
    }

    /**
     * @param string    $url
     * @param int       $position
     *
     * @return \Magento\Framework\DataObject
     */
    private function createImageObject($url, $position)
    {
        return new \Magento\Framework\DataObject([
            'id'       => rand(0, 10000),
            'url'      => $url,
            'file'     => $url,
            'position' => $position,
            'tile'     => 'fake image',
            'alt'      => 'fake image'
        ]);
    }

    /**
     * @param array $items
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getTestCollection($items)
    {
        $collection = $this
            ->getMockBuilder(\Magento\Framework\Data\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $iterator = new \ArrayIterator($items);
        $collection->expects($this->any())->method('getIterator')->will($this->returnValue($iterator));

        return $collection;
    }

    /**
     * @return array
     */
    public function productTypeProvider()
    {
        return [
            [Grouped::TYPE_CODE],
            [Product\Type::TYPE_SIMPLE],
            [Product\Type::TYPE_BUNDLE],
            [Product\Type::TYPE_VIRTUAL]
        ];
    }

    /**
     * @return array
     */
    public function displayTypeProvider()
    {
        return [
            'grouped product'      => ['list', Grouped::TYPE_CODE],
            'configurable product' => ['select', Configurable::TYPE_CODE],
            'default type'         => ['simple', 'simple']
        ];
    }

    /**
     * @return array
     */
    public function descriptionProvider()
    {
        return [
            'default long description' => ['long', 'test', 'long', 'short'],
            'return short description' => ['short', Description::ID_SHORT_DESCRIPTION, 'long', 'short']
        ];
    }
}

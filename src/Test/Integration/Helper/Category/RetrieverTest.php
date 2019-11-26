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

namespace Shopgate\Export\Test\Integration\Helper\Category;

use DOMDocument;
use Exception;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Export\Helper\Category\Retriever;
use Shopgate\Export\Model\Export\Category;
use Shopgate_Model_XmlResultObject;

/**
 * @coversDefaultClass \Shopgate\Export\Helper\Category\Retriever
 */
class RetrieverTest extends TestCase
{
    /** @var StoreManagerInterface */
    protected $storeManager;
    /** @var  Retriever */
    protected $class;
    /** @var array */
    protected $categories = [];
    /** @var CategoryFactory */
    protected $categoryFactory;

    /**
     * Init store manager + current class
     */
    public function setUp()
    {
        $objManager            = Bootstrap::getObjectManager();
        $this->storeManager    = $objManager->create('Magento\Store\Model\StoreManagerInterface');
        $this->class           = $objManager->create('Shopgate\Export\Helper\Category\Retriever');
        $this->categoryFactory = $objManager->create('Magento\Catalog\Model\CategoryFactory');
    }

    /**
     * Check if the created category can be pulled
     * individually by UID
     *
     * @covers ::buildCategoryTree
     * @covers ::getCategories
     */
    public function testGetCategoriesByUid()
    {
        $category = $this->createCategory();

        $sgCategories = $this->class->getCategories(null, null, [$category->getId()]);

        foreach ($sgCategories as $sgCategory) {
            /** @var Category $sgCategory */
            $this->assertEquals($category->getId(), $sgCategory->getUid());
        }
    }

    /**
     * @param $expected - expected number of items in collection
     * @param $limit
     * @param $offset
     *
     * @covers ::buildCategoryTree
     * @covers ::getCategories
     * @dataProvider limitProvider
     */
    public function testGetCategoriesLimit($expected, $limit, $offset)
    {
        if (is_null($expected)) {
            $rootId   = $this->storeManager->getGroup()->getRootCategoryId();
            $expected = $this
                ->categoryFactory
                ->create()
                ->getCollection()
                ->addFieldToFilter('path', ['like' => '%' . $rootId . '/%'])
                ->getSize();
        }
        $sgCategories = $this->class->getCategories($limit, $offset, []);

        $this->assertCount($expected, $sgCategories);
    }

    /**
     * Checks current export with online Schema
     *
     * @requires function fopen
     * @requires function libxml_set_external_entity_loader
     *
     * @uses     \DOMDocument::loadXML
     * @uses     \DOMDocument::schemaValidateSource
     *
     * @covers ::buildCategoryTree
     * @covers ::getCategories
     */
    public function testExportXml()
    {
        $category = $this->createCategory();
        $result   = $this->class->getCategories(null, null, [$category->getId()]);
        /** @var Category $sgCategory */
        $sgCategory = array_pop($result);
        $node       = new Shopgate_Model_XmlResultObject('<categories></categories>');
        $xml        = $sgCategory->asXml($node)->asXML();
        $result     = $this->checkXmlFile($sgCategory->getXsdFileLocation(), $xml);

        $this->assertTrue($result, 'Category schema does not match what is exported');
    }

    /**
     * @param string $xsdFile    - online file location
     * @param string $currentXml - xml of the object
     *
     * @return bool
     */
    public function checkXmlFile($xsdFile, $currentXml)
    {
        $dom = new DOMDocument();
        $dom->loadXML($currentXml);
        $schema = file_get_contents($xsdFile);

        libxml_use_internal_errors(true);
        $result = $dom->schemaValidateSource($schema);
        libxml_use_internal_errors(false);

        return $result;
    }

    /**
     * Provider for limits and offsets
     *
     * @return array
     */
    public function limitProvider()
    {
        return [
            'no limit test'             => [null, null, null],
            'first 5'                   => [5, 5, 0],
            'next 3'                    => [3, 3, 5],
            'no offset defaults to all' => [null, 5, null],
            'no limit default to all'   => [null, null, 0],
            'non-existing offset'       => [0, 5, 1000]
        ];
    }

    /**
     * Remove all created categories.
     * Area security is required to delete categories.
     */
    public function tearDown()
    {
        /**
         * @var Registry $registry
         */
        $registry = Bootstrap::getObjectManager()->get('\Magento\Framework\Registry');
        $registry->register('isSecureArea', true, true);

        foreach ($this->categories as $category) {
            $category->delete();
        }
    }

    /**
     * Creates a category
     *
     * @return \Magento\Catalog\Model\Category
     * @throws Exception
     */
    private function createCategory()
    {
        $rootId = $this->storeManager->getGroup()->getRootCategoryId();

        /** @var CategoryFactory $categoryFactory */
        $category = $this->categoryFactory->create();
        $category
            ->setIsActive(1)
            ->setName('Test' . rand(0, 999))
            ->setParentId($rootId)
            ->setPath('1/' . $rootId)
            ->setUrlPath('deep_link/url.html')
            ->setData('is_anchor', 1)
            ->setData('sort_order', '15');
        $this->categories[] = $category->save();

        return $category;
    }
}

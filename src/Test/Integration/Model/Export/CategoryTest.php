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

use Magento\Catalog\Model\CategoryFactory;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Base\Tests\Integration\Db\ConfigManager;
use Shopgate\Export\Api\ExportInterface;
use Shopgate\Export\Model\Export\CategoryFactory as ExportFactory;

/**
 * @coversDefaultClass Shopgate\Export\Model\Export\Category
 */
class CategoryTest extends TestCase
{
    /** @var ExportFactory */
    protected $sgCategoryFactory;
    /** @var CategoryFactory */
    protected $mageCategoryFactory;
    /** @var  ConfigManager */
    protected $cfgManager;

    public function setUp()
    {
        $this->cfgManager          = new ConfigManager;
        $objManager                = Bootstrap::getObjectManager();
        $this->sgCategoryFactory   = $objManager->create('Shopgate\Export\Model\Export\CategoryFactory');
        $this->mageCategoryFactory = $objManager->create('Magento\Catalog\Model\CategoryFactory');
    }

    /**
     * Checks regular export & forced rewrite via core_config_data setting
     *
     * @param int   $expected          - expected isActive result
     * @param int   $isActive          - is the current category active
     * @param int   $currentCategoryId - current category id being exported
     * @param array $inactiveList      - list of categories to force to export
     *
     * @covers ::setIsActive
     * @covers ::isActiveForceRewrite
     * @dataProvider isActiveForceProvider
     */
    public function testIsActiveForce($expected, $isActive, $currentCategoryId, $inactiveList)
    {
        $this->cfgManager->setConfigValue(ExportInterface::PATH_CAT_FORCE_LIST, $inactiveList);

        $category = $this->mageCategoryFactory->create();
        $category->setIsActive($isActive);
        $category->setId($currentCategoryId);

        $categoryExportModel = $this->sgCategoryFactory->create();
        $categoryExportModel->setItem($category);
        $categoryExportModel->setIsActive();

        $this->assertEquals($expected, $categoryExportModel->getIsActive());
    }

    /**
     * @return array
     */
    public function isActiveForceProvider()
    {
        return [
            'default enabled'               => [
                'expected active'        => 1,
                'initial active'         => 1,
                'current category id'    => 0,
                'inactive to export cfg' => ''
            ],
            'default disabled'              => [
                'expected active'        => 0,
                'initial active'         => 0,
                'current category id'    => 0,
                'inactive to export cfg' => ''
            ],
            'force export current category' => [
                'expected active'        => 1,
                'initial active'         => 0,
                'current category id'    => 5,
                'inactive to export cfg' => '5'
            ],
            'force export category '        => [
                'expected active'        => 1,
                'initial active'         => 0,
                'current category id'    => 5,
                'inactive to export cfg' => '34,5, 7'
            ],
        ];
    }

    /**
     * Tries to export all categories, if includeInMenu is 0
     * it may or may not allow it to be exported as Active
     * based on the core_config_data setting.
     *
     * @param $expected     - expected active result
     * @param $isActive     - is active in category
     * @param $inMenu       - is included in menu
     * @param $inMenuConfig - config forcing to activate only included in menu categories
     *
     * @covers ::setIsActive
     * @covers ::isActiveInMenuOnly
     * @dataProvider isActiveMenuProvider
     */
    public function testIsActiveInMenu($expected, $isActive, $inMenu, $inMenuConfig)
    {
        $this->cfgManager->setConfigValue(ExportInterface::PATH_CAT_NAV_ONLY, $inMenuConfig);

        $category = $this->mageCategoryFactory->create();
        $category->setIsActive($isActive);
        $category->setIncludeInMenu($inMenu);

        $categoryExportModel = $this->sgCategoryFactory->create();
        $categoryExportModel->setItem($category);
        $categoryExportModel->setIsActive();

        $this->assertEquals($expected, $categoryExportModel->getIsActive());
    }

    /**
     * All tests must have category active at first
     * as the test checks if it becomes inactive in
     * certain conditions.
     *
     * @return array
     */
    public function isActiveMenuProvider()
    {
        return [
            'default test'                     => [
                'expected is active'          => 1,
                'current cat is active'       => 1,
                'current cat include in menu' => 1,
                'include in menu only cfg'    => 0,
            ],
            'export all categories'            => [
                'expected is active'          => 1,
                'current cat is active'       => 1,
                'current cat include in menu' => 0,
                'include in menu only cfg'    => 0,
            ],
            'active and included'              => [
                'expected is active'          => 1,
                'current cat is active'       => 1,
                'current cat include in menu' => 1,
                'include in menu only cfg'    => 1,
            ],
            'only included in menu categories' => [
                'expected is active'          => 0,
                'current cat is active'       => 1,
                'current cat include in menu' => 0,
                'include in menu only cfg'    => 1,
            ],
        ];
    }

    /**
     * @after
     */
    public function cleanup()
    {
        $this->cfgManager->removeConfigs();
    }
}

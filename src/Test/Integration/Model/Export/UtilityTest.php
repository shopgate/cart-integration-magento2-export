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

use PHPUnit\Framework\TestCase;
use Shopgate\Base\Api\Config\SgCoreInterface;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Base\Tests\Integration\Db\ConfigManager;
use Shopgate\Export\Model\Export\Utility;

/**
 * @coversDefaultClass \Shopgate\Export\Model\Export\Utility
 */
class UtilityTest extends TestCase
{
    /** @var  Utility */
    protected $class;
    /** @var  ConfigManager */
    protected $cfgManager;

    /**
     * Set up
     */
    public function setUp(): void
    {
        $this->cfgManager = new ConfigManager;
        $objManager       = Bootstrap::getObjectManager();
        $this->class      = $objManager->create(Utility::class);
    }

    /**
     * Tests our url parser that inserts user:pass
     * into the given link
     *
     * @param string $expected
     * @param string $url
     * @param string $user
     * @param string $password
     *
     * @covers ::parseUrl
     * @dataProvider parseUrlProvider
     */
    public function testParseUrl($expected, $url, $user, $password): void
    {
        $this->cfgManager->setConfigValue(SgCoreInterface::PATH_HTUSER, $user);
        $this->cfgManager->setConfigValue(SgCoreInterface::PATH_HTPASS, $password);

        $returned = $this->class->parseUrl($url);

        $this->assertEquals($expected, $returned);
    }

    /**
     * @return array
     */
    public function parseUrlProvider(): array
    {
        return [
            [
                'returned url' => 'http://user:pass@shopgate.com',
                'initial url'  => 'http://shopgate.com',
                'user'         => 'user',
                'password'     => 'pass',
            ],
            [
                'returned url' => 'http://shopgate.com',
                'initial url'  => 'http://shopgate.com',
                'user'         => '',
                'password'     => 'pass',
            ],
            [
                'returned url' => 'http://shopgate.com',
                'initial url'  => 'http://shopgate.com',
                'user'         => 'user',
                'password'     => '',
            ],
            [
                'returned url' => 'https://user:pass@shopgate.com',
                'initial url'  => 'https://shopgate.com',
                'user'         => 'user',
                'password'     => 'pass',
            ],
        ];
    }

    /**
     * Remove database entries
     */
    public function tearDown(): void
    {
        $this->cfgManager->removeConfigs();
    }
}

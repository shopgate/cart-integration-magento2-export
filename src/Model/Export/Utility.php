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

namespace Shopgate\Export\Model\Export;

use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Api\Config\SgCoreInterface;

class Utility
{
    /** @var CoreInterface */
    private $coreInterface;

    /**
     * Utility constructor.
     *
     * @param CoreInterface $coreInterface
     *
     * @codeCoverageIgnore
     */
    public function __construct(CoreInterface $coreInterface)
    {
        $this->coreInterface = $coreInterface;
    }

    /**
     * Inject htaccess user & password in the link if they are enabled
     *
     * @param string $url - url to inject the user/pass into
     *
     * @return string
     */
    public function parseUrl($url)
    {
        $htuser = $this->coreInterface->getConfigByPath(SgCoreInterface::PATH_HTUSER)->getData('value');
        $htpass = $this->coreInterface->getConfigByPath(SgCoreInterface::PATH_HTPASS)->getData('value');

        if ($url && $htuser && $htpass) {
            $replacement = parse_url($url, PHP_URL_SCHEME) . '://';
            $replacement .= urlencode($htuser);
            $replacement .= ':';
            $replacement .= urlencode($htpass);
            $replacement .= '@';
            $url = preg_replace('/^(http|https):\/\//i', $replacement, $url, 1);
        }

        return $url;
    }
}

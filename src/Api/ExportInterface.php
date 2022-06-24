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

namespace Shopgate\Export\Api;

interface ExportInterface extends \Shopgate\Base\Api\ExportInterface
{
    const PATH_CAT_FORCE_LIST         = self::PATH_CATEGORIES . '/force';
    const PATH_CAT_NAV_ONLY           = self::PATH_CATEGORIES . '/nav_only';
    const PATH_PROD_DESCRIPTION       = self::PATH_PRODUCTS . '/description';
    const PATH_PROD_CHILD_DESCRIPTION = self::PATH_PRODUCTS . '/child_description';
    const PATH_PROD_OUT_OF_STOCK      = self::PATH_PRODUCTS . '/out_of_stock';
}

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

namespace Shopgate\Export\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ChildDescription implements ArrayInterface
{
    /**
     * Define ids for child description export
     */
    const ID_CHILD_ONLY        = 0;
    const ID_PARENT_ONLY       = 1;
    const ID_BOTH_PARENT_FIRST = 2;
    const ID_BOTH_CHILD_FIRST  = 3;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::ID_CHILD_ONLY, 'label' => __('Child\'s Only')],
            ['value' => self::ID_PARENT_ONLY, 'label' => __('Parent\'s Only')],
            ['value' => self::ID_BOTH_PARENT_FIRST, 'label' => __('Both: Parent\'s First')],
            ['value' => self::ID_BOTH_CHILD_FIRST, 'label' => __('Both: Child\'s First')],
        ];
    }
}

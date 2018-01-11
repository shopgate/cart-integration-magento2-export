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

class Description implements ArrayInterface
{
    /**
     * Define ids for description export
     */
    const ID_DESCRIPTION                       = 0;
    const ID_SHORT_DESCRIPTION                 = 1;
    const ID_DESCRIPTION_AND_SHORT_DESCRIPTION = 2;
    const ID_SHORT_DESCRIPTION_AND_DESCRIPTION = 3;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::ID_DESCRIPTION, 'label' => __('Description')],
            ['value' => self::ID_SHORT_DESCRIPTION, 'label' => __('Short description')],
            [
                'value' => self::ID_DESCRIPTION_AND_SHORT_DESCRIPTION,
                'label' => __('Description') . " + " . __('Short description')
            ],
            [
                'value' => self::ID_SHORT_DESCRIPTION_AND_DESCRIPTION,
                'label' => __('Short description') . " + " . __('Description')
            ],
        ];
    }
}

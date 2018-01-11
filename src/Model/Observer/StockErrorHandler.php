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

namespace Shopgate\Export\Model\Observer;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Shopgate\Base\Model\Shopgate\Extended\Base;
use Shopgate\Base\Model\Utility\Registry;

class StockErrorHandler implements ObserverInterface
{
    /** @var Base */
    private $cart;
    /** @var Registry */
    private $registry;
    /** @var array - list of allowed actions */
    private $actionWhiteList = ['check_stock'];

    /**
     * @param Base     $cart
     * @param Registry $registry
     */
    public function __construct(Base $cart, Registry $registry)
    {
        $this->cart     = $cart;
        $this->registry = $registry;
    }

    /**
     * Remove errors from configurable products without attribute selections.
     * Since SG sends a check_stock request on page load, some times the children
     * are not selected for configurable products, so instead of throwing an error
     * we pass the validation.
     *
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        if (!$this->registry->isActionInList($this->actionWhiteList)) {
            return;
        }

        /** @var \Magento\Quote\Model\Quote\Item $item */
        $item     = $observer->getData('item');
        $error    = $item->getData('has_error');
        $cartItem = $this->cart->getItemById($item->getData('product_id'));

        if ($error
            && $item->getProductType() === Configurable::TYPE_CODE
            && $cartItem
            && $cartItem->isSimple()
        ) {
            $item->setHasError(false);
        }

        return;
    }
}

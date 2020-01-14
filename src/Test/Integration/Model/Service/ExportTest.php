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

namespace Shopgate\Export\Test\Integration;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Shopgate\Base\Tests\Bootstrap;
use Shopgate\Base\Tests\Integration\Db\StockManager;
use Shopgate\Export\Helper\Cart;
use Shopgate\Export\Model\Service\Export as ExportModel;
use Zend_Json_Decoder;
use Zend_Json_Exception;

/**
 * @coversDefaultClass \Shopgate\Export\Model\Service\Export
 * @magentoAppIsolation enabled
 * @magentoDbIsolation  enabled
 */
class ExportTest extends TestCase
{

    /**
     * @var StockManager
     */
    protected $stockManager;

    public function setUp(): void
    {
        $this->stockManager = new StockManager();
    }

    /**
     * Passes all products inside the cart and
     * tests if all return is_buyable correctly
     *
     * @param int   $expected
     * @param array $sgCart
     *
     * @covers       ::checkCart
     * @covers       ::checkCartRaw
     * @covers       Cart::__construct
     * @covers       Cart::loadSupportedMethods
     * @covers       Cart::getItems
     * @covers       Quote::setItems
     * @covers       Quote::setCustomer
     *
     * @dataProvider allProductProvider
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartItems(int $expected, array $sgCart): void
    {
        $internalInfo = Zend_Json_Decoder::decode($sgCart['cart']['items'][0]['internal_order_info']);
        $this->stockManager->setStockWebsite($internalInfo['product_id']);

        /** @var ExportModel $class */
        $class  = Bootstrap::getObjectManager()->create(ExportModel::class);
        $return = $class->checkCart($sgCart);
        $item   = array_pop($return['items']);

        $this->assertEquals($expected, $item['is_buyable']);
    }

    /**
     * Merges all providers together
     *
     * @return array
     */
    public function allProductProvider(): array
    {
        return array_merge(
            $this->simpleProductProvider(),
            $this->groupProductProvider(),
            $this->bundledProductProvider(),
            $this->configurableProductProvider()
        );
    }

    /**
     * @return array
     */
    public function simpleProductProvider(): array
    {
        return [
            'simple product: success' => [
                'expected' => 1,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    'item_number'          => '3',
                                    'item_number_public'   => null,
                                    'parent_item_number'   => '',
                                    'quantity'             => 1,
                                    'unit_amount_net'      => 34.0000,
                                    'unit_amount_with_tax' => 3,
                                    'unit_amount'          => 34.0000,
                                    'name'                 => 'Simple',
                                    'tax_percent'          => 20.00,
                                    'currency'             => 'EUR',
                                    'internal_order_info'  => '{"product_id":"3", "item_type":"simple"}',
                                    'is_free_shipping'     => '',
                                    'attributes'           => [],
                                    'inputs'               => [],
                                    'options'              => [],
                                ],
                            ],
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function groupProductProvider(): array
    {
        return [
            'group product: success' => [
                'expected' => 1,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    "item_id"              => 24195039,
                                    "item_number"          => '2046-33', //34, 35 too
                                    "item_number_public"   => null,
                                    "parent_item_number"   => '',
                                    "quantity"             => 1,
                                    "unit_amount_net"      => 34.0000,
                                    "unit_amount_with_tax" => 3,
                                    "unit_amount"          => 34.0000,
                                    "name"                 => 'Grouped',
                                    "tax_percent"          => 20.00,
                                    "tax_class"            => 241269,
                                    "currency"             => 'EUR',
                                    "internal_order_info"  =>
                                        '{"store_view_id":"3", "product_id":"33", "item_type":"grouped"}',
                                    "is_free_shipping"     => '',
                                    "attributes"           => [],
                                    "inputs"               => [],
                                    "options"              => [],
                                ],
                            ],
                    ]
                ]
            ]
        ];
    }

    /**
     * Bundled product tests
     *
     * @return array
     */
    public function bundledProductProvider(): array
    {
        return [
            'bundled product: success'                             => [
                'expected' => 1,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    'item_number'          => '2045',
                                    'quantity'             => 1,
                                    'unit_amount_net'      => 2.5,
                                    'unit_amount_with_tax' => 3,
                                    'unit_amount'          => 2.5,
                                    'name'                 => 'Bundled',
                                    'tax_percent'          => 20.00,
                                    'internal_order_info'  => '{
                                         "store_view_id":"1",
                                         "product_id":"2045",
                                         "item_type":"bundle",
                                         "exchange_rate":1}',
                                    'attributes'           => [],
                                    'inputs'               => [],
                                    'options'              => [
                                        [
                                            //Input UID (catalog_product_bundle_selection.option_id)
                                            'option_number'     => '1',
                                            //Input default name
                                            'name'              => 'Zing Jump Rope',
                                            //Selection ID (.selection_id)
                                            'value_number'      => '1',
                                            //Product Title
                                            'value'             => 'Ropes',
                                            //extra cost for selection
                                            'additional_amount' => '0'
                                        ],
                                        [
                                            'option_number'     => '2',
                                            'name'              => 'Zing Jump Rope',
                                            'value_number'      => '4',
                                            'value'             => 'Ropes',
                                            'additional_amount' => '0'
                                        ],
                                        [
                                            'option_number'     => '3',
                                            'name'              => 'Zing Jump Rope',
                                            'value_number'      => '5',
                                            'value'             => 'Ropes',
                                            'additional_amount' => '0'
                                        ],
                                        [
                                            'option_number'     => '4',
                                            'name'              => 'Zing Jump Rope',
                                            'value_number'      => '8',
                                            'value'             => 'Ropes',
                                            'additional_amount' => '0'
                                        ]
                                    ],
                                ],
                            ],
                    ]
                ]
            ],
            'bundled product: fail, not all req. options provided' => [
                'expected' => 0,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    'item_number'          => '2045',
                                    'quantity'             => 1,
                                    'unit_amount_net'      => 2.5,
                                    'unit_amount_with_tax' => 3,
                                    'unit_amount'          => 2.5,
                                    'name'                 => 'Bundled',
                                    'tax_percent'          => 20.00,
                                    'internal_order_info'  => '{
                                         "store_view_id":"1",
                                         "product_id":"2045",
                                         "item_type":"bundle",
                                         "exchange_rate":1}',
                                    'attributes'           => [],
                                    'inputs'               => [],
                                    'options'              => [
                                        [
                                            'option_number'     => '1',
                                            'name'              => 'Zing Jump Rope',
                                            'value_number'      => '1',
                                            'value'             => 'Ropes',
                                            'additional_amount' => '0'
                                        ],
                                    ],
                                ],
                            ],
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function configurableProductProvider(): array
    {
        return [
            'configurable product: success' => [
                'expected' => 1,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    "item_number"                        => "66-51",
                                    "item_number_public"                 => null,
                                    "parent_item_number"                 => '',
                                    "quantity"                           => 1,
                                    "unit_amount_net"                    => 2.5,
                                    "unit_amount_with_tax"               => 3,
                                    "unit_amount"                        => 2.5,
                                    "name"                               => 'Configurable',
                                    "tax_percent"                        => 20.00,
                                    "internal_order_info"                =>
                                        '{"product_id":"51","item_type":"configurable"}',
                                    "additional_shipping_costs_per_unit" => 0,
                                    "is_free_shipping"                   => '',
                                    "attributes"                         => [
                                        [
                                            'name'  => 'Color', //90
                                            'value' => 'Black' //49
                                        ],
                                        [
                                            'name'  => 'Size', //137
                                            'value' => 'XS' //167
                                        ],
                                    ],
                                    "inputs"                             => [],
                                    "options"                            => [],
                                ],
                            ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Resetting quote to save a new
     * quote for each iteration
     *
     * @after
     */
    public function removeQuoteItems(): void
    {
        //todo-sg: does not clear quote correctly between tests
        /** @var Quote $quote */
        $quote = Bootstrap::getObjectManager()->get('Magento\Quote\Model\Quote');
        $quote->removeAllItems();
        $quote->isDeleted(false);
        $quote->isObjectNew(true);
        $quote->setId(null);
    }

    /**
     * @param bool  $expected
     * @param array $cart
     *
     * @dataProvider couponProvider
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartCoupons(bool $expected, array $cart): void
    {
        $internalInfo = Zend_Json_Decoder::decode($cart['cart']['items'][0]['internal_order_info']);
        $this->stockManager->setStockWebsite($internalInfo['product_id']);

        /** @var ExportModel $class */
        $class  = Bootstrap::getObjectManager()->create(ExportModel::class);
        $return = $class->checkCart($cart);
        $coupon = array_pop($return['external_coupons']);

        $this->assertEquals($expected, $coupon['is_valid']);
    }

    /**
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 0
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 0
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsWithTaxNetNoCrossBoarder(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 16.2375,
            'tax_percent'     => 8.25,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     *
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 0
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 1
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsWithTaxNetAndCrossBoarder(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 16.2375,
            'tax_percent'     => 8.25,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     *
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 1
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 0
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsWithTaxGross(): void
    {
        $expectedAmounts = [
            'amount'          => 13.8568,
            'amount_with_tax' => 15,
            'tax_percent'     => 8.25,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 1
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 1
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsWithTaxGrossAndBorderTrade(): void
    {
        $expectedAmounts = [
            'amount'          => 13.8568,
            'amount_with_tax' => 15,
            'tax_percent'     => 8.25,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 0
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 0
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsNetWithoutTaxNoCrossBoarder(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 15,
            'tax_percent'     => 0,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 0
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 1
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsNetWithoutTaxAndCrossBorder(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 15,
            'tax_percent'     => 0,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 1
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 0
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsNetWithTaxNoCrossBoarder(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 15,
            'tax_percent'     => 0,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 1
     * @magentoConfigFixture current_store tax/calculation/cross_border_trade_enabled 1
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsNetWithTaxNetAndBorderTrade(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 15,
            'tax_percent'     => 0,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 0
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    public function testCheckCartShippingMethodsWithoutTax(): void
    {
        $expectedAmounts = [
            'amount'          => 15,
            'amount_with_tax' => 15,
            'tax_percent'     => 0,
        ];

        $this->runCheckCartShippingTest($expectedAmounts);
    }

    /**
     * @param array $expectedAmounts
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws Zend_Json_Exception
     */
    private function runCheckCartShippingTest(array $expectedAmounts): void
    {
        $cart         = $this->getCartForShippingTests();
        $internalInfo = Zend_Json_Decoder::decode($cart['cart']['items'][0]['internal_order_info']);
        $this->stockManager->setStockWebsite($internalInfo['product_id']);

        /** @var ExportModel $class */
        $class           = Bootstrap::getObjectManager()->create(ExportModel::class);
        $return          = $class->checkCart($cart);
        $shippingMethod  = array_pop($return['shipping_methods']);

        $this->assertCount(1, $return['shipping_methods']);
        $this->assertEquals($expectedAmounts['amount'], $shippingMethod['amount']);
        $this->assertEquals($expectedAmounts['amount_with_tax'], $shippingMethod['amount_with_tax']);
        $this->assertEquals($expectedAmounts['tax_percent'], $shippingMethod['tax_percent']);
    }

    /**
     * @return array
     */
    public function getCartForShippingTests(): array
    {
        return [
            'cart' => [
                'external_customer_number' => '1',
                'mail'                     => 'roni_cost@example.com',
                'delivery_address'         => [
                    'gender'     => 'm',
                    'first_name' => 'roni',
                    'last_name'  => 'cost',
                    'street_1'   => '1247  D Street',
                    'city'       => 'Bloomfield Township',
                    'zipcode'    => '48302',
                    'country'    => 'US',
                    'state'      => 'US-MI',
                    'phone'      => '123456789'
                ],
                'items'                    => [
                    [
                        'item_number'          => '3',
                        'item_number_public'   => null,
                        'parent_item_number'   => '',
                        'quantity'             => 1,
                        'unit_amount_net'      => 34.0000,
                        'unit_amount_with_tax' => 3,
                        'unit_amount'          => 34.0000,
                        'name'                 => 'Simple',
                        'tax_percent'          => 20.00,
                        'currency'             => 'EUR',
                        'internal_order_info'  => '{"product_id":"3", "item_type":"simple"}',
                        'is_free_shipping'     => '',
                        'attributes'           => [],
                        'inputs'               => [],
                        'options'              => [],
                    ],
                ],
            ]
        ];
    }

    /**
     * @return array
     */
    public function couponProvider(): array
    {
        return [
            'H20 + id3 == failure'  => [
                'expected' => 0,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    'internal_order_info' => '{"product_id":"3", "item_type":"simple"}',
                                ],
                            ],
                        'external_coupons'         => [
                            [
                                'is_valid'          => null,
                                'not_valid_message' => null,
                                'order_index'       => null,
                                'code'              => 'H20',
                                'name'              => null,
                                'description'       => '',
                                'amount'            => '',
                                'amount_net'        => '',
                                'amount_gross'      => '',
                                'tax_type'          => 'auto',
                                'currency'          => '',
                                'is_free_shipping'  => false,
                                'internal_info'     => ''
                            ],
                        ]
                    ]
                ]
            ],
            'H20 + id15 == success' => [
                'expected' => 1,
                'case'     => [
                    'cart' => [
                        'external_customer_number' => '1',
                        'mail'                     => 'roni_cost@example.com',
                        'items'                    =>
                            [
                                [
                                    'internal_order_info' => '{"product_id":"15", "item_type":"simple"}',
                                ],
                            ],
                        'external_coupons'         => [
                            [
                                'is_valid'          => null,
                                'not_valid_message' => null,
                                'order_index'       => null,
                                'code'              => 'H20',
                                'name'              => null,
                                'description'       => '',
                                'amount'            => '',
                                'amount_net'        => '',
                                'amount_gross'      => '',
                                'tax_type'          => 'auto',
                                'currency'          => '',
                                'is_free_shipping'  => false,
                                'internal_info'     => ''
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }
}

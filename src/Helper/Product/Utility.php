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

namespace Shopgate\Export\Helper\Product;

use Exception;
use Magento\Bundle\Model\Product\Price;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Bundle\Model\ResourceModel\Option\Collection as OptionCollection;
use Magento\Bundle\Model\ResourceModel\Selection\Collection as SelectionCollection;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product as MageProduct;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Tax\Model\TaxCalculation;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Export\Api\ExportInterface;
use Shopgate\Export\Helper\Product\Stock\Factory;
use Shopgate\Export\Helper\Product\Stock\UtilityInventorySalesApi as StockUtility;
use Shopgate\Export\Model\Config\Source\Description;
use Shopgate\Export\Model\Export\Utility as ExportUtility;
use Shopgate\Export\Model\Shopgate\Product\StockItem;
use Shopgate_Model_Catalog_CategoryPath;
use Shopgate_Model_Catalog_Input;
use Shopgate_Model_Catalog_Option;
use Shopgate_Model_Catalog_Product;
use Shopgate_Model_Catalog_Relation;
use Shopgate_Model_Catalog_Validation;
use Shopgate_Model_Catalog_Visibility;

use function in_array;

class Utility
{
    /** Identifier manufacturer */
    public const DEFAULT_ATTRIBUTE_MANUFACTURER = 'manufacturer';
    /** Identifier price type percent */
    public const DEFAULT_PRICE_TYPE_PERCENT = 'percent';
    /** Default description linebreak format */
    private const DEFAULT_DESCRIPTION_LINEBREAK_PATTERN = '%s<br /><br />%s';

    /** @var array */
    protected $inputTypes = [
        'field' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TEXT,
        'area' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_AREA,
        'select' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT,
        'drop_down' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT,
        'radio' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT,
        'checkbox' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT,
        'multiple' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT,
        'multi' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT,
        'date' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_DATE,
        'date_time' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_DATETIME,
        'time' => Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TIME
    ];
    /** @var array */
    protected $weightUnits = [
        'kgs' => Shopgate_Model_Catalog_Product::DEFAULT_WEIGHT_UNIT_KG,
        'g' => Shopgate_Model_Catalog_Product::DEFAULT_WEIGHT_UNIT_GRAM,
        'auto' => Shopgate_Model_Catalog_Product::DEFAULT_WEIGHT_UNIT_DEFAULT,
        'lb' => Shopgate_Model_Catalog_Product::DEFAULT_WEIGHT_UNIT_POUND,
        'oz' => Shopgate_Model_Catalog_Product::DEFAULT_WEIGHT_UNIT_OUNCE
    ];
    /** @var StoreManager */
    protected $storeManager;
    /** @var TaxCalculation */
    protected $taxCalculation;
    /** @var ExportUtility */
    protected $utility;
    /** @var TaxConfig */
    protected $taxConfig;
    /** @var CategoryRepositoryInterface */
    protected $categoryRepository;
    /** @var FilterProvider */
    protected $filter;
    /** @var CoreInterface */
    protected $sgCore;
    /** @var StockUtility */
    protected $stockUtility;

    /**
     * @param StoreManager $storeManager
     * @param TaxCalculation $taxCalculation
     * @param ExportUtility $utility
     * @param CategoryRepositoryInterface $categoryRepository
     * @param TaxConfig $taxConfig
     * @param FilterProvider $filter
     * @param CoreInterface $sgCore
     * @param Factory $stockUtilityFactory
     */
    public function __construct(
        StoreManager $storeManager,
        TaxCalculation $taxCalculation,
        ExportUtility $utility,
        CategoryRepositoryInterface $categoryRepository,
        TaxConfig $taxConfig,
        FilterProvider $filter,
        CoreInterface $sgCore,
        Factory $stockUtilityFactory
    ) {
        $this->storeManager = $storeManager;
        $this->taxCalculation = $taxCalculation;
        $this->utility = $utility;
        $this->categoryRepository = $categoryRepository;
        $this->taxConfig = $taxConfig;
        $this->filter = $filter;
        $this->sgCore = $sgCore;
        $this->stockUtility = $stockUtilityFactory->getUtility();
    }

    /**
     * @param MageProduct $product
     *
     * @return bool
     */
    public function isVisibleInCategories($product): bool
    {
        $validVisibilities = [
            Visibility::VISIBILITY_BOTH,
            Visibility::VISIBILITY_IN_CATALOG
        ];

        return in_array((int)$product->getVisibility(), $validVisibilities, true);
    }

    /**
     * @param MageProduct $product
     *
     * @return string|null
     */
    public function getManufacturer($product): ?string
    {
        $manufacturer = $product->getManufacturer();

        if ($manufacturer) {
            $manufacturer =
                $product->getResource()
                    ->getAttribute(self::DEFAULT_ATTRIBUTE_MANUFACTURER)
                    ->getSource()
                    ->getOptionText($manufacturer);
        }

        return $manufacturer;
    }

    /**
     * @param MageProduct $product
     *
     * @return Shopgate_Model_Catalog_Visibility
     */
    public function setVisibility($product): Shopgate_Model_Catalog_Visibility
    {
        $visibility = new Shopgate_Model_Catalog_Visibility();
        switch ($product->getVisibility()) {
            case Visibility::VISIBILITY_BOTH:
                $level = Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_CATALOG_AND_SEARCH;
                break;
            case Visibility::VISIBILITY_IN_CATALOG:
                $level = Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_CATALOG;
                break;
            case Visibility::VISIBILITY_IN_SEARCH:
                $level = Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_SEARCH;
                break;
            case Visibility::VISIBILITY_NOT_VISIBLE:
                $level = Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_NOTHING;
                break;
            default:
                $level = null;
        }

        $visibility->setLevel($level);
        $visibility->setMarketplace(true);

        return $visibility;
    }

    /**
     * @param MageProduct $product
     *
     * @return StockItem
     * @throws LocalizedException
     */
    public function getStockItem($product): StockItem
    {
        return $product->getTypeId() == BundleType::TYPE_CODE
            ? $this->setStockQuantityForBundleProduct($product, $this->stockUtility->getStockItem($product))
            : $this->stockUtility->getStockItem($product);
    }

    /**
     * @param MageProduct $product
     * @param StockItem $stockItem
     *
     * @return StockItem
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InputException
     */
    protected function setStockQuantityForBundleProduct(MageProduct $product, StockItem $stockItem): StockItem
    {
        $selectionQuantities = [];
        foreach ($this->getSelectionsCollection($product) as $selection) {
            $selectionId = $selection->getSelectionId();
            $quantity = $this->stockUtility->getStockQuantityBySku($selection->getSku());
            $selectionQuantities[$selectionId] = isset($quantity[0]) ? $quantity[0]['qty'] : 0;
        }

        $stockItem->setStockQuantity(count($selectionQuantities) ? min($selectionQuantities) : 0);

        return $stockItem;
    }

    /**
     * @param string $mageType
     *
     * @return mixed
     */
    public function mapInputType($mageType)
    {
        return $this->inputTypes[$mageType] ?? false;
    }

    /**
     * @param string $weightUnit
     *
     * @return string
     */
    public function getWeightUnit($weightUnit): string
    {
        return $this->weightUnits[$weightUnit] ?? $weightUnit;
    }

    /**
     * @param string $inputType
     * @param Option $option
     *
     * @return Shopgate_Model_Catalog_Validation
     */
    public function buildInputValidation($inputType, $option): Shopgate_Model_Catalog_Validation
    {
        $validation = new Shopgate_Model_Catalog_Validation();

        switch ($inputType) {
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TEXT:
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_AREA:
                $validationType = Shopgate_Model_Catalog_Validation::DEFAULT_VALIDATION_VARIABLE_STRING;
                $validation->setValue($option->getMaxCharacters());
                break;
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_FILE:
                $validationType = Shopgate_Model_Catalog_Validation::DEFAULT_VALIDATION_TYPE_FILE;
                $validation->setValue($option->getFileExtension());
                break;
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_DATE:
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_DATETIME:
                $validationType = Shopgate_Model_Catalog_Validation::DEFAULT_VALIDATION_VARIABLE_DATE;
                break;
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TIME:
                $validationType = Shopgate_Model_Catalog_Validation::DEFAULT_VALIDATION_VARIABLE_TIME;
                break;
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_SELECT:
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_RADIO:
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_CHECKBOX:
            case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_MULTIPLE:
                $validationType = Shopgate_Model_Catalog_Validation::DEFAULT_VALIDATION_VARIABLE_STRING;
                break;
            default:
                return $validation;
        }

        $validation->setValidationType($validationType);

        return $validation;
    }

    /**
     * @param Option $option
     * @param MageProduct $item
     *
     * @return mixed
     */
    public function getInputValuePrice($option, $item)
    {
        return $this->getOptionValuePrice($option, $item);
    }

    /**
     * @param Option | ProductCustomOptionValuesInterface $option
     * @param MageProduct $item
     *
     * @return mixed
     */
    public function getOptionValuePrice($option, $item)
    {
        if ($option->getPriceType() === self::DEFAULT_PRICE_TYPE_PERCENT) {
            return $item->getFinalPrice() * ($option->getPrice() / 100);
        }

        return $option->getPrice();
    }

    /**
     * @param array $relationIds
     * @param string $type
     *
     * @return Shopgate_Model_Catalog_Relation
     */
    public function createRelationProducts(array $relationIds, $type): Shopgate_Model_Catalog_Relation
    {
        $relationModel = new Shopgate_Model_Catalog_Relation();
        $relationModel->setType($type);
        $relationModel->setValues($relationIds);

        return $relationModel;
    }

    /**
     * @param Option $option
     * @param MageProduct $item
     *
     * @return array
     */
    public function buildInputOptions($option, $item): array
    {
        $optionValues = [];

        foreach ($option->getValues() as $id => $value) {
            $inputOption = new Shopgate_Model_Catalog_Option();
            $inputOption->setUid($id);
            $inputOption->setLabel($value->getTitle());
            $inputOption->setSortOrder($value->getSortOrder());
            $inputOption->setAdditionalPrice($this->getOptionValuePrice($value, $item));
            $optionValues[] = $inputOption;
        }

        return $optionValues;
    }

    /**
     * @param MageProduct $item
     *
     * @return float
     */
    public function getTaxRate($item): float
    {
        return $this->taxCalculation->getDefaultCalculatedRate($item->getTaxClassId());
    }

    /**
     * @param MageProduct $item
     * @param MageProduct $parentItem
     *
     * @return string
     */
    public function getDeepLinkUrl($item, $parentItem = null): string
    {
        return $parentItem && (int)$item->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE
            ? $this->utility->parseUrl($parentItem->getProductUrl())
            : $this->utility->parseUrl($item->getProductUrl());
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function priceIncludesTax(): bool
    {
        return $this->taxConfig->priceIncludesTax($this->storeManager->getStore());
    }

    /**
     * Create a category export for a product
     *
     * @param string | int $categoryId
     * @param ?int $position
     *
     * @return Shopgate_Model_Catalog_CategoryPath
     */
    public function getExportCategory($categoryId, $position = null): Shopgate_Model_Catalog_CategoryPath
    {
        $category = new Shopgate_Model_Catalog_CategoryPath();
        $category->setSortOrder($position ?: 0);
        $category->setUid($categoryId);

        return $category;
    }

    /**
     * @param int | string $productId
     * @param int | string $categoryId
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getPositionInCategory($productId, $categoryId): int
    {
        /** @var Category $category */
        $category = $this->categoryRepository->get($categoryId);
        $positions = $category->getProductsPosition();

        return (int)isset($positions[$productId]) ? $positions[$productId] : 1;
    }

    /**
     * Returns the description of current product as per
     * Shopgate Sys > Config instructions
     *
     * @param MageProduct $product
     *
     * @return string
     * @throws Exception
     */
    public function getIndividualDescription(Product $product): string
    {
        $descriptionConfig = $this->sgCore->getConfigByPath(ExportInterface::PATH_PROD_DESCRIPTION);

        switch ($descriptionConfig->getValue()) {
            case Description::ID_DESCRIPTION_AND_SHORT_DESCRIPTION:
                $description = sprintf(
                    self::DEFAULT_DESCRIPTION_LINEBREAK_PATTERN,
                    $product->getData('description'),
                    $product->getData('short_description')
                );
                break;
            case Description::ID_SHORT_DESCRIPTION_AND_DESCRIPTION:
                $description = sprintf(
                    self::DEFAULT_DESCRIPTION_LINEBREAK_PATTERN,
                    $product->getData('short_description'),
                    $product->getData('description')
                );
                break;
            case Description::ID_SHORT_DESCRIPTION:
                $description = $product->getData('short_description');
                break;
            default:
                $description = $product->getData('description');
        }

        return $this->filter->getPageFilter()->filter((string)$description);
    }

    /**
     * @param array $inputs
     * @param MageProduct $selection
     * @param MageProduct $item
     */
    public function addBundleInputOption(array $inputs, MageProduct $selection, MageProduct $item)
    {
        foreach ($inputs as $input) {
            if ($input->getUid() === $selection->getOptionId()) {
                $qty = max(1, (int)$selection->getSelectionQty());
                $inputItem = new Shopgate_Model_Catalog_Option();
                $inputItem->setUid($selection->getSelectionId());
                $inputItem->setLabel(
                    $qty > 1
                        ? sprintf('%d x %s', $qty, $selection->getName())
                        : $selection->getName()
                );

                $inputItem->setAdditionalPrice(
                    $item->getPriceType() == Price::PRICE_TYPE_DYNAMIC
                        ? $selection->getSelectionCanChangeQty() == 0 && $qty > 1
                        ? $selection->getPrice() * $qty
                        : $selection->getPrice()
                        : $selection->getSelectionPriceValue()
                );

                $input->addOption($inputItem);
            }
        }
    }

    /**
     * @param MageProduct $item
     *
     * @return OptionCollection
     */
    public function getOptionsCollection(Product $item): OptionCollection
    {
        return $item->getTypeInstance(true)->getOptionsCollection($item);
    }

    /**
     * @param MageProduct $item
     *
     * @return SelectionCollection
     */
    public function getSelectionsCollection(Product $item): SelectionCollection
    {
        return $item->getTypeInstance(true)->getSelectionsCollection(
            $item->getTypeInstance(true)->getOptionsIds($item),
            $item
        );
    }
}

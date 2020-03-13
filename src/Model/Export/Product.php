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

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product as MageProduct;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\GroupManagement;
use Magento\Directory\Helper\Data;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Base\Helper\Product\Type;
use Shopgate\Base\Model\Utility\SgLoggerInterface;
use Shopgate\Export\Api\ExportInterface;
use Shopgate\Export\Helper\Product\Utility as HelperProduct;
use Shopgate\Export\Model\Config\Source\ChildDescription;
use Shopgate\Export\Model\Export\ProductFactory as ExportFactory;
use Shopgate_Model_Catalog_Attribute;
use Shopgate_Model_Catalog_AttributeGroup;
use Shopgate_Model_Catalog_Identifier;
use Shopgate_Model_Catalog_Input;
use Shopgate_Model_Catalog_Manufacturer;
use Shopgate_Model_Catalog_Price;
use Shopgate_Model_Catalog_Product;
use Shopgate_Model_Catalog_Property;
use Shopgate_Model_Catalog_Relation;
use Shopgate_Model_Catalog_Shipping;
use Shopgate_Model_Catalog_Stock;
use Shopgate_Model_Catalog_Tag;
use Shopgate_Model_Catalog_TierPrice;
use Shopgate_Model_Media_Image;
use Zend_Date;
use Zend_Json;

class Product extends Shopgate_Model_Catalog_Product
{
    /** @var MageProduct */
    protected $item;
    /** @var MageProduct */
    protected $parent;
    /** @var ImageHelper */
    private $imageHelper;
    /** @var array */
    protected $fireMethods = [
        'setLastUpdate',
        'setUid',
        'setName',
        'setTaxPercent',
        'setTaxClass',
        'setCurrency',
        'setDescription',
        'setDeeplink',
        'setPromotionSortOrder',
        'setInternalOrderInfo',
        'setAgeRating',
        'setWeight',
        'setWeightUnit',
        'setPrice',
        'setShipping',
        'setManufacturer',
        'setVisibility',
        'setStock',
        'setImages',
        'setCategoryPaths',
        'setProperties',
        'setIdentifiers',
        'setTags',
        'setRelations',
        'setAttributeGroups',
        'setInputs',
        'setAttachments',
        'setChildren',
        'setDisplayType',
        'setAttributes'
    ];
    /** @var Type */
    private $type;
    /** @var SgLoggerInterface */
    private $logger;
    /** @var CoreInterface */
    private $scopeConfig;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var HelperProduct */
    private $helperProduct;
    /** @var ExportFactory */
    private $exportFactory;
    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /**
     * @param CoreInterface               $scopeConfig
     * @param StoreManagerInterface       $storeManager
     * @param HelperProduct               $helperProduct
     * @param ExportFactory               $exportFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Type                        $type
     * @param ImageHelper                 $imageHelper
     * @param SgLoggerInterface           $logger
     */
    public function __construct(
        CoreInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        HelperProduct $helperProduct,
        ExportFactory $exportFactory,
        CategoryRepositoryInterface $categoryRepository,
        Type $type,
        ImageHelper $imageHelper,
        SgLoggerInterface $logger
    ) {
        parent::__construct();
        $this->scopeConfig        = $scopeConfig;
        $this->storeManager       = $storeManager;
        $this->helperProduct      = $helperProduct;
        $this->exportFactory      = $exportFactory;
        $this->categoryRepository = $categoryRepository;
        $this->type               = $type;
        $this->imageHelper        = $imageHelper;
        $this->logger             = $logger;
    }

    /**
     * Set product id
     */
    public function setUid(): void
    {
        parent::setUid($this->item->getId());
    }

    /**
     * Set last updated update date
     */
    public function setLastUpdate(): void
    {
        parent::setLastUpdate(date(Zend_Date::ISO_8601, strtotime($this->item->getUpdatedAt())));
    }

    /**
     * Set category name
     */
    public function setName(): void
    {
        parent::setName($this->item->getName());
    }

    /**
     * Set tax percent
     */
    public function setTaxPercent(): void
    {
        parent::setTaxPercent($this->helperProduct->getTaxRate($this->item));
    }

    /**
     * Set taxClassId
     */
    public function setTaxClass(): void
    {
        $taxClassId = $this->item->getTaxClassId();
        if ($taxClassId) {
            parent::setTaxClass($taxClassId);
        }
    }

    /**
     * Set currency
     */
    public function setCurrency(): void
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        parent::setCurrency($store->getCurrentCurrency()->getCode());
    }

    /**
     * Set price
     */
    public function setPrice(): void
    {
        $isGross   = $this->helperProduct->priceIncludesTax();
        $priceType = $isGross
            ? Shopgate_Model_Catalog_Price::DEFAULT_PRICE_TYPE_GROSS
            : Shopgate_Model_Catalog_Price::DEFAULT_PRICE_TYPE_NET;

        $priceModel = new Shopgate_Model_Catalog_Price();
        $priceModel->setPrice($this->item->getPrice());

        if ($priceModel->getPrice() > 0) {
            $priceModel->setSalePrice($this->item->getFinalPrice());
        }
        $priceModel->setMsrp($this->item->getMsrp());
        $priceModel->setCost($this->item->getCost());
        $priceModel->setType($priceType);

        foreach ($this->item->getTierPrices() as $tierPrice) {
            $tierPriceModel = new Shopgate_Model_Catalog_TierPrice();
            $tierPriceModel->setFromQuantity($tierPrice->getQty());
            $tierPriceModel->setReduction($priceModel->getSalePrice() - $tierPrice->getValue());
            $tierPriceModel->setReductionType(Shopgate_Model_Catalog_TierPrice::DEFAULT_TIER_PRICE_TYPE_FIXED);

            if ($tierPrice->getCustomerGroupId() !== GroupManagement::CUST_GROUP_ALL) {
                $tierPriceModel->setCustomerGroupUid($tierPrice->getCustomerGroupId());
            }
            $priceModel->addTierPriceGroup($tierPriceModel);
        }

        parent::setPrice($priceModel);
    }

    /**
     * Set description
     *
     * @throws Exception
     */
    public function setDescription(): void
    {
        $description = $this->helperProduct->getIndividualDescription($this->item);
        if ($this->parent) {
            $type              = $this->scopeConfig->getConfigByPath(ExportInterface::PATH_PROD_CHILD_DESCRIPTION);
            $parentDescription = $this->helperProduct->getIndividualDescription($this->parent);
            switch ($type->getValue()) {
                case ChildDescription::ID_CHILD_ONLY:
                    //intentionally omitted
                    break;
                case ChildDescription::ID_BOTH_PARENT_FIRST:
                    $description = $parentDescription . $description;
                    break;
                case ChildDescription::ID_BOTH_CHILD_FIRST:
                    $description .= $parentDescription;
                    break;
                case ChildDescription::ID_PARENT_ONLY:
                    $description = $parentDescription;
                    break;
                default:
                    $description = '';
                    break;
            }
        }

        parent::setDescription($description);
    }

    /**
     * Set deep link
     */
    public function setDeeplink(): void
    {
        parent::setDeeplink($this->helperProduct->getDeepLinkUrl($this->item));
    }

    /**
     * Set promotion sort order
     */
    public function setPromotionSortOrder(): void
    {
        //ToDo implement promotion logic in Magento
        parent::setPromotionSortOrder(false);
    }

    /**
     * Set internal order info
     */
    public function setInternalOrderInfo(): void
    {
        $internalOrderInfo = [
            'store_view_id' => $this->item->getStoreId(),
            'product_id'    => $this->item->getId(),
            'item_type'     => $this->item->getTypeId()
        ];
        if ($this->parent instanceof MageProduct) {
            $internalOrderInfo['parent_sku'] = $this->parent->getSku();
            $internalOrderInfo['item_type']  = $this->parent->getTypeId();
        }

        parent::setInternalOrderInfo(Zend_Json::encode($internalOrderInfo));
    }

    /**
     * Set age rating
     */
    public function setAgeRating(): void
    {
        parent::setAgeRating(false);
    }

    /**
     * Set weight
     */
    public function setWeight(): void
    {
        parent::setWeight(
            floatval(str_replace(',', '.', $this->item->getWeight()))
        );
    }

    /**
     * Set weight unit
     */
    public function setWeightUnit(): void
    {
        $weightUnit = $this->helperProduct->getWeightUnit(
            $this->scopeConfig->getConfigByPath(Data::XML_PATH_WEIGHT_UNIT)->getValue()
        );

        parent::setWeightUnit($weightUnit);
    }

    /**
     * Set shipping
     */
    public function setShipping(): void
    {
        $shipping = new Shopgate_Model_Catalog_Shipping();
        $shipping->setAdditionalCostsPerUnit(0);
        $shipping->setCostsPerOrder(0);
        $shipping->setIsFree(false);

        parent::setShipping($shipping);
    }

    /**
     * Inflates the sort for products with in the
     * actual categories, the IsAnchor categories
     * that inherit should have their sort order
     * low so that they position lower in Shopgate
     * Mobile. In Shopgate position 0 is last.
     */
    public function setCategoryPaths(): void
    {
        if (!$this->helperProduct->isVisibleInCategories($this->item)) {
            return;
        }

        $result      = [];
        $sortInflate = 1000000;

        foreach ($this->item->getCategoryIds() as $categoryId) {
            try {
                /** @var \Magento\Catalog\Model\Category $category */
                $category            = $this->categoryRepository->get($categoryId);
                $position            = $this->helperProduct->getPositionInCategory($this->item->getId(), $categoryId);
                $result[$categoryId] = $this->helperProduct->getExportCategory($categoryId, $sortInflate - $position);

                $anchors = $category->getAnchorsAbove();
                foreach ($anchors as $anchorId) {
                    if (isset($result[$anchorId])) {
                        continue;
                    }
                    $position          = $this->helperProduct->getPositionInCategory($this->item->getId(), $anchorId);
                    $result[$anchorId] = $this->helperProduct->getExportCategory($anchorId, $position);
                }
            } catch (Exception $e) {
                $this->logger->error(
                    "Skip assigning of category with id: {$categoryId}, message: " . $e->getMessage()
                );
            }
        }

        parent::setCategoryPaths($result);
    }

    /**
     * Set manufacturer
     */
    public function setManufacturer(): void
    {
        $title = $this->helperProduct->getManufacturer($this->item);

        if (!empty($title)) {
            $manufacturer = new Shopgate_Model_Catalog_Manufacturer();
            $manufacturer->setUid($this->item->getManufacturer());
            $manufacturer->setTitle($title);
            $manufacturer->setItemNumber(false);
            parent::setManufacturer($manufacturer);
        }
    }

    /**
     * Set visibility
     */
    public function setVisibility(): void
    {
        parent::setVisibility(
            $this->helperProduct->setVisibility($this->item)
        );
    }

    /**
     * Set stock
     *
     * @throws LocalizedException
     */
    public function setStock(): void
    {
        $stockItem = $this->helperProduct->getStockItem($this->item);
        $stock     = new Shopgate_Model_Catalog_Stock();
        $stock->setData($stockItem->getData());

        parent::setStock($stock);
    }

    /**
     * Set images
     */
    public function setImages(): void
    {
        $images        = [];
        $galleryImages = $this->item->getMediaGalleryImages();

        if (is_object($galleryImages)) {
            foreach ($galleryImages as $image) {
                $smallImage = $this->item->getData('small_image');

                /** @var $image DataObject */
                $imageModel = new Shopgate_Model_Media_Image();
                $imageModel->setUid($image->getData('id'));
                $imageModel->setUrl($image->getData('url'));
                $imageModel->setSortOrder($image->getData('position'));
                $imageModel->setTitle($image->getData('label'));
                $imageModel->setAlt($image->getData('label'));

                if ($smallImage === $image->getData('file')) {
                    $images[-1] = $imageModel;
                    $imageModel->setIsCover(true);
                } else {
                    $images[] = $imageModel;
                }
            }

            ksort($images);
        }
        if (empty($images)) {
            $url        = $this->imageHelper->init($this->item, 'product_base_image')->getUrl();
            $imageModel = (new Shopgate_Model_Media_Image())->setUrl($url);
            $images     = [$imageModel];
        }

        parent::setImages($images);
    }

    /**
     * Set identifiers
     */
    public function setIdentifiers(): void
    {
        $identifierItemObject = new Shopgate_Model_Catalog_Identifier();
        $identifierItemObject->setType('SKU');
        $identifierItemObject->setValue($this->item->getSku());
        $result[] = $identifierItemObject;

        parent::setIdentifiers($result);
    }

    /**
     * Set tags
     */
    public function setTags(): void
    {
        $result = [];
        $tags   = explode(',', $this->item->getMetaKeyword());

        foreach ($tags as $tag) {
            if (!ctype_space($tag) && !empty($tag)) {
                $tagItemObject = new Shopgate_Model_Catalog_Tag();
                $tagItemObject->setValue(trim($tag));
                $result[] = $tagItemObject;
            }
        }

        parent::setTags($result);
    }

    /**
     * Set relations
     */
    public function setRelations(): void
    {
        $result      = [];
        $relationIds = array_merge(
            $this->item->getCrossSellProductIds(),
            $this->item->getUpSellProductIds(),
            $this->item->getRelatedProductIds()
        );
        if (!empty($relationIds)) {
            $result[] = $this->helperProduct->createRelationProducts(
                $relationIds,
                Shopgate_Model_Catalog_Relation::DEFAULT_RELATION_TYPE_UPSELL
            );
        }

        parent::setRelations($result);
    }

    /**
     * Set properties
     */
    public function setProperties(): void
    {
        $result          = [];
        $forceAttributes = explode(
            ',',
            $this->scopeConfig->getConfigByPath(ExportInterface::PATH_PROD_FORCE_ATTRIBUTES)->getValue()
        );
        $eanMapping      = $this->scopeConfig->getConfigByPath(ExportInterface::PATH_PROD_EAN_CODE)->getValue();

        foreach ($this->item->getAttributes() as $code => $attribute) {
            $forceExport = in_array($code, $forceAttributes, true);
            if ($forceExport || $attribute->getIsVisibleOnFront() || $eanMapping === $attribute->getAttributeCode()) {
                $value = $attribute->getFrontend()->getValue($this->item);
                if (!empty($value) && ($this->item->hasData($code) || $forceExport)) {
                    $propertyModel = new Shopgate_Model_Catalog_Property();
                    $propertyModel->setUid($attribute->getAttributeId());
                    $propertyModel->setLabel($attribute->getDefaultFrontendLabel());
                    $propertyModel->setValue($value);
                    $result[] = $propertyModel;
                }
            }
        }

        parent::setProperties($result);
    }

    /**
     * Set options
     */
    public function setInputs(): void
    {
        $result = [];

        foreach ($this->item->getOptions() as $option) {
            /** @var MageProduct\Option $option */
            $inputType = $this->helperProduct->mapInputType($option->getType());
            if ($inputType === false) {
                continue;
            }

            $inputItem = new Shopgate_Model_Catalog_Input();
            $inputItem->setUid($option->getId());
            $inputItem->setType($inputType);
            $inputItem->setLabel($option->getTitle());
            $inputItem->setRequired($option->getIsRequire());
            $inputItem->setValidation($this->helperProduct->buildInputValidation($inputType, $option));
            $inputItem->setSortOrder($option->getSortOrder());

            /**
             * Add additional price for types without options
             */
            switch ($inputType) {
                case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TEXT:
                case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_AREA:
                case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_FILE:
                case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_DATE:
                case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_DATETIME:
                case Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TIME:
                    $inputItem->setAdditionalPrice($this->helperProduct->getInputValuePrice($option, $this->item));
                    break;
                default:
                    $inputItem->setOptions($this->helperProduct->buildInputOptions($option, $this->item));
                    break;
            }

            $result[] = $inputItem;
        }

        parent::setInputs($result);
    }

    /**
     * set attribute groups
     */
    public function setAttributeGroups(): void
    {
        if ($this->item->getTypeId() == Configurable::TYPE_CODE) {
            $configurableAttributes = $this->item
                ->getTypeInstance()
                ->getConfigurableAttributes($this->item);
            $attributeGroups        = [];
            foreach ($configurableAttributes as $attribute) {
                /* @var $attribute Configurable\Attribute */
                $attributeItem = new Shopgate_Model_Catalog_AttributeGroup();
                $attributeItem->setUid($attribute->getAttributeId());
                $attributeItem->setLabel($attribute->getProductAttribute()->getFrontend()->getLabel());
                $attributeGroups[] = $attributeItem;
            }
            parent::setAttributeGroups($attributeGroups);
        }
    }

    /**
     * Set the attributes + values for children
     */
    public function setAttributes(): void
    {
        $attributes = [];
        if ($this->getIsChild()
            && $this->parent->getTypeId() === Configurable::TYPE_CODE
        ) {
            /** @var Configurable $productTypeInstance */
            $productTypeInstance    = $this->parent->getTypeInstance();
            $configurableAttributes = $productTypeInstance
                ->getConfigurableAttributes($this->parent);
            foreach ($configurableAttributes as $attribute) {
                /** @var Configurable\Attribute $attribute */
                $attributeValue = $this->item
                    ->getResource()
                    ->getAttribute($attribute->getProductAttribute()->getAttributeCode())
                    ->getFrontend()
                    ->getValue($this->item);

                $itemAttribute = new Shopgate_Model_Catalog_Attribute();
                $itemAttribute->setGroupUid($attribute->getAttributeId());
                $itemAttribute->setLabel($attributeValue);
                $attributes[] = $itemAttribute;
            }
        }
        parent::setAttributes($attributes);
    }

    /**
     * Generate children.
     * Skip check if it's already a child.
     */
    public function setChildren(): void
    {
        if ($this->parent) {
            return;
        }

        $children      = [];
        $childProducts = $this->type->getType($this->item)->getChildren();
        foreach ($childProducts as $childProduct) {
            $productExportModel = $this->exportFactory->create();
            $productExportModel->setItem($childProduct);
            $productExportModel->setParentItem($this->item);
            $productExportModel->setIsChild(true);
            $productExportModel->generateData();
            $productExportModel->setData('uid', $this->item->getId() . '-' . $childProduct->getId());
            $children[] = $productExportModel;
            $childProduct->clearInstance();
        }

        parent::setChildren($children);
    }

    /**
     * @param MageProduct $parent
     */
    public function setParentItem(MageProduct $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * Set displayType
     */
    public function setDisplayType(): void
    {
        switch ($this->item->getTypeId()) {
            case Grouped::TYPE_CODE:
                parent::setDisplayType(Shopgate_Model_Catalog_Product::DISPLAY_TYPE_LIST);
                break;
            case Configurable::TYPE_CODE:
                parent::setDisplayType(Shopgate_Model_Catalog_Product::DISPLAY_TYPE_SELECT);
                break;
            default:
                parent::setDisplayType(Shopgate_Model_Catalog_Product::DISPLAY_TYPE_SIMPLE);
                break;
        }
    }
}

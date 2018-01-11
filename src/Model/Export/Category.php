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

use Magento\Catalog\Model\Category as MageCategory;
use Shopgate\Base\Api\Config\CoreInterface;
use Shopgate\Export\Api\ExportInterface;

class Category extends \Shopgate_Model_Catalog_Category
{
    /** @var MageCategory */
    protected $item;
    /** @var null */
    protected $parentId = null;
    /** @var null | int */
    protected $maxPosition = null;
    /** @var Utility */
    private $utility;
    /** @var CoreInterface */
    private $scopeConfig;

    /**
     * @param Utility       $utility
     * @param CoreInterface $scopeConfig
     */
    public function __construct(Utility $utility, CoreInterface $scopeConfig)
    {
        $this->utility     = $utility;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param int $position - position of the max category element
     *
     * @return $this
     */
    public function setMaximumPosition($position)
    {
        $this->maxPosition = $position;

        return $this;
    }

    /**
     * @return null | int
     */
    public function getMaximumPosition()
    {
        return $this->maxPosition;
    }

    /**
     * Generate data dom object by firing a method array
     *
     * @return $this
     */
    public function generateData()
    {
        foreach ($this->fireMethods as $method) {
            $this->{$method}($this->item);
        }

        return $this;
    }

    /**
     * Set category id
     */
    public function setUid()
    {
        parent::setUid($this->item->getId());
    }

    /**
     * Set category sort order
     */
    public function setSortOrder()
    {
        parent::setSortOrder($this->getMaximumPosition() - $this->item->getPosition());
    }

    /**
     * Set category name
     */
    public function setName()
    {
        parent::setName($this->item->getName());
    }

    /**
     * Set parent category id
     */
    public function setParentUid()
    {
        parent::setParentUid($this->item->getParentId() != $this->parentId ? $this->item->getParentId() : null);
    }

    /**
     * Category link in shop
     */
    public function setDeeplink()
    {
        parent::setDeeplink($this->getDeepLinkUrl($this->item));
    }

    /**
     * Check if category is anchor
     */
    public function setIsAnchor()
    {
        parent::setIsAnchor($this->item->getData('is_anchor'));
    }

    /**
     * @param $parentId
     *
     * @return $this
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * @param MageCategory $category
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getImageUrl($category)
    {
        return $this->utility->parseUrl($category->getImageUrl());
    }

    /**
     * @param MageCategory $category
     *
     * @return string
     */
    private function getDeepLinkUrl($category)
    {
        return $this->utility->parseUrl($category->getUrl());
    }

    /**
     * Set category image
     */
    public function setImage()
    {
        if ($this->item->getImageUrl()) {
            $imageItem = new \Shopgate_Model_Media_Image();

            $imageItem->setUid(1);
            $imageItem->setSortOrder(1);
            $imageItem->setUrl($this->getImageUrl($this->item));
            $imageItem->setTitle($this->item->getName());

            parent::setImage($imageItem);
        }
    }

    /**
     * Set active state
     */
    public function setIsActive()
    {
        $isActive = $this->item->getIsActive();
        $isActive = $this->isActiveInMenuOnly($isActive);
        $isActive = $this->isActiveForceRewrite($isActive);

        parent::setIsActive($isActive);
    }

    /**
     * Checks if the category is forced to be enabled by merchant
     * via the Stores > Config value
     *
     * @param int $isActive
     *
     * @return int
     */
    private function isActiveForceRewrite($isActive)
    {
        if ($isActive == 1) {
            return (int)$isActive;
        }

        $catIds      = $this->scopeConfig->getConfigByPath(ExportInterface::PATH_CAT_FORCE_LIST)->getData('value');
        $catIdsArray = array_map('trim', explode(',', $catIds));

        if (empty($catIds)) {
            return (int)$isActive;
        }

        if ((in_array($this->item->getId(), $catIdsArray)
            || array_intersect(
                $catIdsArray,
                $this->item->getParentIds()
            ))
        ) {
            $isActive = 1;
        }

        return $isActive;
    }

    /**
     * Checks if the category is forced to be disabled
     * by navigation only setting. Does not affect forced
     * check.
     *
     * @param int $isActive
     *
     * @return int
     */
    private function isActiveInMenuOnly($isActive)
    {
        if ($isActive == 0) {
            return $isActive;
        }

        if ($this->scopeConfig->getConfigByPath(ExportInterface::PATH_CAT_NAV_ONLY)->getData('value')
            && !$this->item->getIncludeInMenu()
        ) {
            $isActive = 0;
        }

        return $isActive;
    }
}

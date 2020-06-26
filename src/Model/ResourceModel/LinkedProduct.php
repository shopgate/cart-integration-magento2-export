<?php

namespace Shopgate\Export\Model\ResourceModel;

class LinkedProduct extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {
    public const GROUPED = \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED;
    public const CROSSSELL = \Magento\Catalog\Model\Product\Link::LINK_TYPE_CROSSSELL;
    public const RELATED = \Magento\Catalog\Model\Product\Link::LINK_TYPE_RELATED;
    public const UPSELL = \Magento\Catalog\Model\Product\Link::LINK_TYPE_UPSELL;

    /**
     * Define main table name and attributes table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('catalog_product_link', 'link_id');
    }

    /**
     * Retrieve parent ids array by required child
     *
     * @param int|array $linkedProductIds
     * @param int $typeId
     * @return string[]
     */
    public function getLinkRelationByLinkedProductIds($linkedProductIds, $typeId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from(
            $this->getMainTable(),
            ['product_id', 'linked_product_id AS parent_id']
        )->where(
            'linked_product_id IN(?)',
            $linkedProductIds
        )->where(
            'link_type_id = ?',
            $typeId
        );

        return $connection->fetchAll($select);
    }
}

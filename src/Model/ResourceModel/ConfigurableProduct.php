<?php

namespace Shopgate\Export\Model\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Product\Relation as ProductRelation;
use Magento\Framework\Model\ResourceModel\Db\Context as DbContext;
use Magento\ConfigurableProduct\Model\AttributeOptionProviderInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Attribute\OptionProvider;
use Magento\Framework\App\ScopeResolverInterface;

class ConfigurableProduct extends \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
{
    /** @var OptionProvider $optionProvider */
    private $optionProvider;

    /**
     * @inheritdoc
     */
    public function __construct(
        DbContext $context,
        ProductRelation $catalogProductRelation,
        OptionProvider $optionProvider,
        $connectionName = null,
        ScopeResolverInterface $scopeResolver = null,
        AttributeOptionProviderInterface $attributeOptionProvider = null
    ) {
        parent::__construct($context, $catalogProductRelation, $connectionName, $scopeResolver, $attributeOptionProvider, $optionProvider);
        $this->optionProvider = $optionProvider;
    }

    /**
     * Retrieve parent ids array by required children ids.
     * (basically same as getParentIdsByChild but allows fetching more than single column from result)
     *
     * @param int|array $childIds
     * @return object[]
     */
    public function getParentAndChildIdsByChildIds($childIds)
    {
        $select = $this->getConnection()
            ->select()
            ->from(['l' => $this->getMainTable()], ['l.parent_id', 'l.product_id'])
            ->join(
                ['e' => $this->getTable('catalog_product_entity')],
                'e.' . $this->optionProvider->getProductEntityLinkField() . ' = l.parent_id',
                ['e.entity_id']
            )->where('l.product_id IN(?)', $childIds);

        return $this->getConnection()->fetchAll($select);
    }
}

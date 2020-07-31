<?php

namespace Shopgate\Export\Model\Export\Product;

use Magento\Catalog\Model\Product\Type\Simple;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * This class is a composit part of \Shopgate\Export\Model\Export\Product class and serves in iterative assembling, processing and disassembling of product relations.
 */
class Relation
{
    private const CROSS_SELL        = 'cross_sell';
    private const UPSELL            = 'upsell';
    private const RELATED           = 'related';
    private const CLASSIFIERS       = [self::CROSS_SELL, self::UPSELL, self::RELATED];

    /** Lookup assigned classifiers by productId */
    private $classifierLookup       = [];
    /** Lookup relations in combination productId and classifier */
    private $relationsLookup        = [];
    /** Direct relation ids for which no lookup is needed */
    private $relationIds            = [];
    /** Collects product ids during iterative processing of relations */
    private $productIdsByClassifier = [];
    
    /**
     * __construct
     *
     * @param  array $crossSell Cross-Sell magento products
     * @param  array $upsell Upsell magento products
     * @param  array $relatedProducts Related magento products
     * @return void
     */
    public function __construct(array $crossSell, array $upsell, array $relatedProducts)
    {
        $this->init($crossSell, $upsell, $relatedProducts);
    }
    
    /**
     * getCrossSellIds
     *
     * @return array
     */
    public function getCrossSellIds() : array
    {
        return $this->productIdsByClassifier[self::CROSS_SELL];
    }
    
    /**
     * getUpsellIds
     *
     * @return array
     */
    public function getUpsellIds() : array
    {
        return $this->productIdsByClassifier[self::UPSELL];
    }
    
    /**
     * getRelatedProductIds
     *
     * @return array
     */
    public function getRelatedProductIds() : array
    {
        return $this->productIdsByClassifier[self::RELATED];
    }
    
    /**
     * processRelations
     *
     * @param  array $rows Rows with relations ['product_id', 'parent_id']
     * @return void
     */
    public function processRelations(array $rows) : void
    {
        if (empty($rows)) {
            return;
        }

        $productIds = array_column($rows, 'product_id');
        foreach (self::CLASSIFIERS as $classifier) {
            $productIdsByClassifier = $this->filterIdsByClassifier($productIds, $classifier);
            $this->productIdsByClassifier[$classifier] = array_merge(
                $this->productIdsByClassifier[$classifier],
                $this->generateUidsFromResult(
                    $this->filterByProductIds(
                        $rows,
                        $productIdsByClassifier
                    )
                )
            );

            foreach ($productIdsByClassifier as $id) {
                unset($this->relationsLookup[$this->productIdToRelation($id, $classifier)]);
            }
        }
    }
    
    /**
     * hasUnprocessedRelations
     *
     * @return bool
     */
    public function hasUnprocessedRelations() : bool
    {
        return !empty($this->relationsLookup);
    }
    
    /**
     * getUnprocessedRelationIds
     *
     * @return int[]
     */
    public function getUnprocessedRelationIds() : array
    {
        return array_unique(array_values($this->relationsLookup));
    }
    
    /**
     * processRemainingRelationsAsDirectLinks
     *
     * @return void
     */
    public function processRemainingRelationsAsDirectLinks() : void
    {
        $remainingIds = $this->getUnprocessedRelationIds();
        foreach ($remainingIds as $id) {
            $classifiers = $this->classifierLookup[$this->productIdToString($id)];
            foreach ($classifiers as $classifier) {
                $this->productIdsByClassifier[$classifier][] = $id;
            }
        }
        $this->relationsLookup = [];
    }
    
    /**
     * init
     *
     * @param  array $crossSell
     * @param  array $upsell
     * @param  array $relatedProducts
     * @return void
     */
    private function init(array $crossSell, array $upsell, array $relatedProducts) : void
    {
        $products = [
          self::CROSS_SELL => $crossSell,
          self::UPSELL => $upsell,
          self::RELATED => $relatedProducts
        ];

        foreach (self::CLASSIFIERS as $classifier) {
            [$ids, $lookupRelation, $lookupClassifier] = $this->extractRelations($products[$classifier], $classifier);

            $this->relationIds = array_merge($this->relationIds, $ids);
            $this->relationsLookup += $lookupRelation;
            $this->classifierLookup = array_merge_recursive($this->classifierLookup, $lookupClassifier);
        }

        // it may happen that we have relationIds multiple times due to the possibility of them belonging to multiple of relation types/classifiers.
        $this->relationIds = array_unique($this->relationIds);
        // on the init itself we assign direct relations to appropriate relation type/class.
        $this->productIdsByClassifier = [
            self::CROSS_SELL => $this->filterIdsByClassifier($this->relationIds, self::CROSS_SELL),
            self::UPSELL => $this->filterIdsByClassifier($this->relationIds, self::UPSELL),
            self::RELATED => $this->filterIdsByClassifier($this->relationIds, self::RELATED)
        ];
    }
    
    /**
     * filterIdsByClassifier
     *
     * @param  int[] $ids
     * @param  string $classifier
     * @return array
     */
    private function filterIdsByClassifier(array $ids, string $classifier) : array
    {
        return array_filter($ids, function ($id) use ($classifier) {
            return in_array($classifier, $this->classifierLookup[$this->productIdToString($id)]);
        });
    }
    
    /**
     * filterByProductIds
     *
     * @param  array $rows
     * @param  int[] $productIds
     * @return array
     */
    private function filterByProductIds(array $rows, array $productIds) : array
    {
        return array_filter($rows, function ($row) use ($productIds) {
            return in_array($row['product_id'], $productIds);
        });
    }
    
    /**
     * extractRelations
     *
     * @param  array $products
     * @param  string $classifier
     * @return array
     */
    private function extractRelations(array $products, string $classifier) : array
    {
        $relationIds      = [];
        $relationsLookup  = [];
        $classifierLookup = [];
        foreach ($products as $product) {
            $type = $product->getTypeInstance();
            if (!$this->isProductTypeSupported($type)) {
                continue;
            }

            if (!array_key_exists($product->getId(), $classifierLookup)) {
                $classifierLookup[$this->productIdToString($product->getId())] = [];
            }

            $classifierLookup[$this->productIdToString($product->getId())][] = $classifier;
            if ($type instanceof Simple) {
                $relationsLookup[$this->productIdToRelation($product->getId(), $classifier)] = $product->getId();
                continue;
            }

            $relationIds[] = $product->getId();
        }

        return [$relationIds, $relationsLookup, $classifierLookup];
    }
    
    /**
     * productIdToString
     *
     * @param  int $id
     * @return string
     */
    private function productIdToString($id) : string
    {
        return 'p' . $id;
    }
    
    /**
     * productIdToRelation
     *
     * @param  int $id
     * @param  string $classifier
     * @return string
     */
    private function productIdToRelation($id, $classifier) : string
    {
        return $id . '-' . $classifier;
    }
    
    /**
     * generateUidsFromResult
     *
     * @param  array $rows
     * @return array
     */
    private function generateUidsFromResult(array $rows) : array
    {
        $uids = [];
        // we'll reference first parent we find
        foreach ($rows as $row) {
            if (!empty($uids[$row['product_id']])) {
                continue;
            }

            $uids[$row['product_id']] = "{$row['parent_id']}-{$row['product_id']}";
        }

        return $uids;
    }
    
    /**
     * isProductTypeSupported
     *
     * @param  object $typeInstance
     * @return bool
     */
    private function isProductTypeSupported($typeInstance) : bool
    {
        if ($typeInstance instanceof Simple) {
            return true;
        }

        if ($typeInstance instanceof Configurable) {
            return true;
        }

        if ($typeInstance instanceof Grouped) {
            return true;
        }

        return false;
    }
}

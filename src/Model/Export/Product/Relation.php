<?php

namespace Shopgate\Export\Model\Export\Product;

use Magento\Catalog\Model\Product\Type\Simple;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

class Relation
{
    private const CROSS_SELL = 'cross_sell';
    private const UPSELL = 'upsell';
    private const RELATED = 'related';
    private const CLASSIFIERS = [self::CROSS_SELL, self::UPSELL, self::RELATED];

    private $classifierLookup = [];
    private $relationsLookup = [];
    private $relationIds = [];
    private $productIdsByClassifier = [];

    public function __construct(array $crossSell, array $upsell, array $relatedProducts)
    {
        $this->init($crossSell, $upsell, $relatedProducts);
    }

    public function getCrossSellIds() : array
    {
        return $this->productIdsByClassifier[self::CROSS_SELL];
    }

    public function getUpsellIds() : array
    {
        return $this->productIdsByClassifier[self::UPSELL];
    }

    public function getRelatedProductIds() : array
    {
        return $this->productIdsByClassifier[self::RELATED];
    }

    public function processRelations($rows) : void
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

    public function hasUnprocessedRelations() : bool
    {
        return !empty($this->relationsLookup);
    }

    public function getUnprocessedRelationIds() : array
    {
        return array_unique(array_values($this->relationsLookup));
    }

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

        $this->relationIds = array_unique($this->relationIds);
        $this->productIdsByClassifier = [
            self::CROSS_SELL => $this->filterIdsByClassifier($this->relationIds, self::CROSS_SELL),
            self::UPSELL => $this->filterIdsByClassifier($this->relationIds, self::UPSELL),
            self::RELATED => $this->filterIdsByClassifier($this->relationIds, self::RELATED)
        ];
    }

    private function filterIdsByClassifier(array $ids, string $classifier) : array
    {
        return array_filter($ids, function ($id) use ($classifier) {
            return in_array($classifier, $this->classifierLookup[$this->productIdToString($id)]);
        });
    }

    private function filterByProductIds(array $rows, array $productIds) : array
    {
        return array_filter($rows, function ($row) use ($productIds) {
            return in_array($row['product_id'], $productIds);
        });
    }

    private function extractRelations(array $products, string $classifier) : array
    {
        $relationIds = [];
        $relationsLookup = [];
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

    private function productIdToString($id) : string
    {
        return 'p' . $id;
    }

    private function productIdToRelation($id, $classifier) : string
    {
        return $id . '-' . $classifier;
    }

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

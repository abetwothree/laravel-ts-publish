<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

/**
 * Spell the object arm `json_encode()` emits for a collection whose keys are no longer 0..n-1.
 *
 * The single home for this: RelationCollectionChainHandler reaches it from a relation root,
 * CollectionPipelineHandler from a `collect()` root.
 *
 * @internal
 */
trait SpellsKeyedCollections
{
    /**
     * Add the object arm json_encode emits for a gapped or reordered collection: `X[]` → `X[] | Record<string, X>`.
     */
    protected function keyedObjectArm(string $arrayType): string
    {
        return $arrayType.' | Record<string, '.substr($arrayType, 0, -2).'>';
    }
}

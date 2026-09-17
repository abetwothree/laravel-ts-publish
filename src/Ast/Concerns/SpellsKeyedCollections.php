<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;

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

    /**
     * Undo keyedObjectArm(): `X[] | Record<string, X>` → `X[]`, leaving any other type untouched.
     *
     * Reverses only the exact string that method produces, so a union that genuinely carries a
     * `Record` member of its own is never silently narrowed.
     */
    protected function withoutKeyedObjectArm(string $type): string
    {
        $members = TsTypeString::splitTopLevelUnion($type);

        if (count($members) !== 2 || ! str_ends_with($members[0], '[]')) {
            return $type;
        }

        return $type === $this->keyedObjectArm($members[0]) ? $members[0] : $type;
    }
}

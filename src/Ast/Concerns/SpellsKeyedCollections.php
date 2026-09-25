<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * Spell the object arm `json_encode()` emits for a collection whose keys are no longer 0..n-1, and the list `values()`
 * turns it back into: RelationCollectionChainHandler and CollectionPipelineHandler add the arm, and VariableHandler
 * reads a trailing `values()` through the list.
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
     * The list `values()` re-indexes a collection of this type into, arm by arm: `Record<K, V>` → `V[]`, while `V[]`
     * and a short-circuit `null` stay; null when any other arm leaves the element type unknown.
     */
    protected function valuesList(string $type): ?string
    {
        $lists = [];

        foreach (TsTypeString::splitTopLevelUnion($type) as $arm) {
            $record = str_starts_with($arm, 'Record<') ? TsTypeShape::memberType($arm, '') : null;

            $list = match (true) {
                $arm === 'null', TsTypeShape::elementType($arm) !== null => $arm,
                $record !== null => ValueResult::arrayWrapType($record),
                default => null,
            };

            if ($list === null) {
                return null;
            }

            $lists[] = $list;
        }

        return $lists === [] ? null : TsTypeString::hoistNull($lists);
    }
}

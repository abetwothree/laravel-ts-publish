<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;

/**
 * Spell the object arm `json_encode()` emits for a collection whose keys are no longer 0..n-1, the list `values()`
 * turns it back into, and the list a `map()` body makes: RelationCollectionChainHandler, CollectionPipelineHandler and
 * ReceiverMethodReturnResolver add the arm, and VariableHandler reads a trailing `values()` through the list.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
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
        $lists = ArrayArms::arms($type, static function (string $arm): ?string {
            $record = TsTypeShape::recordArguments($arm)[1] ?? null;

            return match (true) {
                $arm === 'null', TsTypeShape::elementType($arm) !== null => $arm,
                $record !== null => ValueResult::arrayWrapType($record),
                default => null,
            };
        }, skipNull: false);

        return $lists === null || $lists === [] ? null : TsTypeString::hoistNull($lists);
    }

    /**
     * The list a `map()` body makes, with the object arm where the collection's keys are out of 0..n-1 and `| null`
     * where a `?->` earlier in the chain can end it as null; null for a body that is null or `unknown`.
     *
     * @param  ValueExpressionResult|null  $body
     * @return ValueExpressionResult|null
     */
    protected function mappedList(?array $body, bool $keyed, bool $shortCircuits): ?array
    {
        if ($body === null || $body['type'] === 'unknown') {
            return null;
        }

        // A union body, such as a mixed AsEnum/direct-enum ternary, is parenthesized before the array suffix binds.
        $list = ValueResult::arrayWrapType($body['type']);
        $type = $keyed ? $this->keyedObjectArm($list) : $list;

        // The body's channels ride along: the transformer rewrites a live `enumFqcn` from an `EnumResource::make()`
        // body in whatever shape results, the keyed Record arm included.
        return [...$body, 'type' => $shortCircuits ? TsTypeString::withNullArm($type) : $type, 'optional' => false];
    }
}

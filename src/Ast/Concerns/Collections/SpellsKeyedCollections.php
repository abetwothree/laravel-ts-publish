<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;

/**
 * Spell the list a `map()` body makes, with the object arm `json_encode()` emits for a collection whose keys are no
 * longer 0..n-1: RelationCollectionChainHandler and VariableHandler map through it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait SpellsKeyedCollections
{
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
        $type = $keyed ? ArrayArms::keyedList($list) : $list;

        // The body's channels ride along: the transformer rewrites a live `enumFqcn` from an `EnumResource::make()`
        // body in whatever shape results, the keyed Record arm included.
        return [...$body, 'type' => $shortCircuits ? TsTypeString::withNullArm($type) : $type, 'optional' => false];
    }
}

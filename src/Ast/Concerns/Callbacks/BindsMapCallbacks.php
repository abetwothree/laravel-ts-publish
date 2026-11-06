<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallableForms;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;

/**
 * The one read of a callback over an array's elements, for `array_map()` and every `map()`. Any callable form reads
 * as the closure it amounts to, whose body ClosureHandler resolves, binding the class an unbound parameter declares.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type LeftOutWrite from ReceiverType
 *
 * @internal
 */
trait BindsMapCallbacks
{
    /**
     * The array a callback maps one array to, each arm keyed as ArrayArms::mapped() keys it. A `null` arm is left out,
     * as PHP throws on it. Null where an arm is no array, or the callback cannot be read for an element.
     *
     * Each element binds as elementOf() reads it, with $elementModel or the model the element names, guarded where
     * $guarded says the array is read off a guarded receiver and no declaration names the model, which reads the
     * callback alone. $arrayed says JsonResource::filter() reaches the result as an array.
     *
     * @param  ValueExpressionResult  $input
     * @param  class-string<Model>|null  $elementModel
     * @param  list<LeftOutWrite>  $leftOut  the writes the join holding the array left out
     * @return ValueExpressionResult|null
     */
    protected function mappedArrayResult(
        Expr $callback,
        array $input,
        bool $arrayed,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?string $elementModel = null,
        bool $guarded = false,
        array $leftOut = [],
    ): ?array {
        $arms = ArrayArms::arms($input['type'], static fn (string $arm): ?string => ArrayArms::elementOf($arm) === null ? null : $arm);

        if ($arms === null || $arms === []) {
            return null;
        }

        /** @var array<string, ValueExpressionResult> $bodies each element's mapped value, read once */
        $bodies = [];
        $mapped = [];

        foreach ($arms as $arm) {
            $element = (string) ArrayArms::elementOf($arm);

            if (! isset($bodies[$element])) {
                $value = ValueResult::elementOf($input, $element);
                $carried = $value['modelFqcn'] ?? null;
                // An element that may be null binds as its value, keeping the `null` a bound model would drop.
                $model = $elementModel ?? ($carried !== null && $element === class_basename($carried) && is_a($carried, Model::class, true)
                    ? $carried
                    : null);
                $body = $this->resolveArrayCallback($callback, [$value], $model, $arrayed, $scope, $engine, guardsElement: $guarded, leftOut: $leftOut);

                if ($body === null || $body['type'] === 'unknown') {
                    return null;
                }

                $bodies[$element] = $body;
            }

            $mapped[] = (string) ArrayArms::mapped($arm, $bodies[$element]['type']);
        }

        $body = count($bodies) === 1 ? array_values($bodies)[0] : ValueResult::unionResults(array_values($bodies));

        // The body's channels ride along, as the transformer rewrites an enum or a resource in whatever shape results.
        return [...$body, 'type' => implode(' | ', array_values(array_unique($mapped))), 'optional' => false];
    }

    /**
     * What a map ending in `toArray()` or `jsonSerialize()` hands filter(), by what its callback returns at the top
     * level. `toArray()` keeps each resource for filter() but turns a Collection into an array filter() enters, and
     * `jsonSerialize()` resolves a bare resource at once, as a kept map does, but keeps one in an array for filter().
     *
     * Null where the callback may return a list or a Record, an array or a Collection alike, or, for `jsonSerialize()`,
     * a resource beside an array.
     *
     * @param  'toArray'|'jsonSerialize'  $serializer
     * @param  Closure(bool): (ValueExpressionResult|null)  $map  the map's result, its callback read as arrayed or not
     * @return ValueExpressionResult|null
     */
    protected function serializedMapResult(string $serializer, Closure $map): ?array
    {
        $mapped = $map($serializer === 'toArray');
        $kinds = $mapped === null ? null : $this->itemKinds($mapped);

        return match (true) {
            $kinds === null || isset($kinds['ambiguous']) => null,
            $serializer === 'toArray' || ! isset($kinds['array']) => $mapped,
            isset($kinds['value']) => null,
            default => $map(true),
        };
    }

    /**
     * What $map makes of $input or, where that leaves a callback unread, of the input $read reads again with each enum
     * case as its enum: a parameter bound to the literal a body fallback reads a case as has no member to read.
     *
     * @template TInput
     *
     * @param  TInput  $input
     * @param  Closure(): (TInput|null)  $read
     * @param  Closure(TInput): (ValueExpressionResult|null)  $map
     * @return ValueExpressionResult|null
     */
    protected function mappedReadingCases(mixed $input, Closure $read, Closure $map, AnalysisScope $scope): ?array
    {
        $mapped = $map($input);

        // Not only in a body fallback: a helper's own body fallback hands any caller its cases as literals.
        if ($mapped !== null) {
            return $mapped;
        }

        $reread = $scope->readingCasesAsEnums($read);

        return $reread === null || $reread === $input ? null : $map($reread);
    }

    /**
     * What a callback returns for one element, as the reading sends it in an array, apart from the callback: each of
     * $parameters bound to the parameter at its position as an array's element, the first with $elementModel and the
     * guards. Null for a callable that reads as no closure, or a variadic first parameter.
     *
     * $arrayed says JsonResource::filter() reaches the result as an array.
     *
     * @param  list<ValueExpressionResult>  $parameters
     * @param  class-string<Model>|null  $elementModel
     * @param  list<LeftOutWrite>  $leftOut  the writes the join holding the element left out
     * @return ValueExpressionResult|null
     */
    protected function resolveArrayCallback(
        Expr $callback,
        array $parameters,
        ?string $elementModel,
        bool $arrayed,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        bool $unread = false,
        bool $guardsElement = false,
        array $leftOut = [],
    ): ?array {
        $callback = CallableForms::asOneValueClosure(CallableForms::heldCallable($callback, $scope), count($parameters));

        if ($callback === null || ($callback->params[0] ?? null)?->variadic === true) {
            return null;
        }

        $returned = $scope->readClaimedClosure(
            $callback,
            null,
            $engine,
            fn (): array => $engine->resolve($callback),
            fn () => ParameterBinder::bindCallbackArguments($callback, $parameters, $scope, $engine, $elementModel, true, $unread, $guardsElement, $leftOut),
            function () use ($scope, $arrayed): void {
                $scope->filtersNestedResources = $scope->filtersNestedResources && $arrayed;
                $scope->mapResultArrayed = false;
            },
        );

        return ValueResult::withoutHeldObject($engine->reading()->arrayValue(ValueResult::calledValue($returned)));
    }

    /**
     * What a `map()` callback returns for one element, its first parameter bound to the element as elementBinding()
     * binds it, guards included, and its second to $keyValue where the caller passes one, as resolveArrayCallback()
     * reads it.
     *
     * $arrayed says JsonResource::filter() reaches the result as an array.
     *
     * @param  class-string<Model>|null  $elementModel
     * @param  ValueExpressionResult|null  $elementValue
     * @param  ValueExpressionResult|null  $keyValue
     * @param  list<LeftOutWrite>  $leftOut  the writes the join holding the element left out
     * @return ValueExpressionResult|null
     */
    protected function resolveMapCallback(
        ?Expr $callback,
        ?string $elementModel,
        ?array $elementValue,
        bool $arrayed,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?array $keyValue = null,
        bool $unread = false,
        bool $guardsElement = false,
        array $leftOut = [],
    ): ?array {
        if ($callback === null) {
            return null;
        }

        $parameters = [$elementValue ?? ValueResult::unknown()];

        if ($keyValue !== null) {
            $parameters[] = $keyValue;
        }

        return $this->resolveArrayCallback($callback, $parameters, $elementModel, $arrayed, $scope, $engine, $unread, $guardsElement, $leftOut);
    }

    /**
     * The kinds of item a map hands back, from its element's top-level arms: `array` for an object literal, `ambiguous`
     * for a list or a Record, and `value` for anything else; a `null` or `[]` arm counts as none. Null where an arm
     * holds no element.
     *
     * @param  ValueExpressionResult  $mapped
     * @return array<'array'|'ambiguous'|'value', true>|null
     */
    private function itemKinds(array $mapped): ?array
    {
        $elements = ArrayArms::arms($mapped['type'], ArrayArms::elementOf(...));

        if ($elements === null) {
            return null;
        }

        $kinds = [];

        foreach (TsTypeShape::splitTopLevel(implode(' | ', $elements), ['|']) as $arm) {
            $kind = match (true) {
                $arm === 'null' || $arm === 'never[]' => null,
                TsTypeShape::members($arm) !== null => 'array',
                ArrayArms::isArray($arm) => 'ambiguous',
                default => 'value',
            };

            if ($kind !== null) {
                $kinds[$kind] = true;
            }
        }

        return $kinds;
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * A literal-key read, `$data['a']` or `$pair[1]`, typed from the member each arm of its holder's type names for the
 * key: `| null` where the member is optional or an arm is `null`, which PHP reads as null.
 *
 * Declines an arm that is no object literal or tuple, since a list, a Record or an index signature names no key the
 * array is sure to hold, an arm lacking the key, an `unknown` member, and a member naming a class, which no import
 * channel carries from here.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class OffsetReadHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [ArrayDimFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof ArrayDimFetch || (! $expr->dim instanceof String_ && ! $expr->dim instanceof Int_)) {
            return null;
        }

        $type = $this->keyType($engine->resolve($expr->var)['type'], (string) $expr->dim->value);

        return $type === null ? null : ['type' => $type, 'optional' => false];
    }

    /**
     * The type a key reads from a holder: each arm's member, and `null` for a `null` arm. Null where an arm declines,
     * or no arm is an array.
     */
    private function keyType(string $holder, string $key): ?string
    {
        $types = [];
        $nullOnly = true;

        foreach (TsTypeShape::splitTopLevel($holder, ['|']) as $arm) {
            $member = $arm === 'null' ? ['null'] : $this->armMember($arm, $key);

            if ($member === null) {
                return null;
            }

            $nullOnly = $nullOnly && $arm === 'null';
            array_push($types, ...$member);
        }

        return $nullOnly ? null : TsTypeString::hoistNull($types);
    }

    /**
     * The types a key reads from an object literal or tuple arm: its member, beside `null` for an optional one. Null
     * where the arm is neither, lacks the key, or its member is `unknown` or names a class.
     *
     * @return list<string>|null
     */
    private function armMember(string $arm, string $key): ?array
    {
        [$member, $optional] = match (true) {
            TsTypeShape::isWrapped($arm, '{', '}') => $this->objectMember($arm, $key),
            TsTypeShape::isWrapped($arm, '[', ']') => $this->tupleMember($arm, $key),
            default => [null, false],
        };

        if ($member === null || $member === 'unknown' || TsTypeString::shapeValueHasUnimportableToken($member)) {
            return null;
        }

        return $optional ? [$member, 'null'] : [$member];
    }

    /**
     * The member an object literal names under a key, written as it is, and whether it is optional; an index
     * signature names no key.
     *
     * @return array{string|null, bool}
     */
    private function objectMember(string $object, string $key): array
    {
        foreach (TsTypeShape::members($object) ?? [] as [$name, $value]) {
            if (trim(rtrim($name, '?'), '\'"') === $key) {
                return [$value, str_ends_with($name, '?')];
            }
        }

        return [null, false];
    }

    /**
     * The element a tuple holds at an index, and whether it is optional; a rest element holds no single index.
     *
     * @return array{string|null, bool}
     */
    private function tupleMember(string $tuple, string $key): array
    {
        $member = TsTypeShape::memberType($tuple, $key);
        $element = TsTypeShape::splitTopLevel(substr($tuple, 1, -1), [','])[(int) $key] ?? '';

        return $member === null || str_starts_with($element, '...') ? [null, false] : [$member, str_ends_with($element, '?')];
    }
}

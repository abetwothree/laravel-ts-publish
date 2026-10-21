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
 * key, with `null` for a `null` arm, which PHP reads as null. An optional member reads optional: a `when*()` slot holds
 * the `MissingValue` an API resource leaves out, and a bare read of a missing key throws, so neither sends `null`.
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

        return $this->keyRead($engine->resolve($expr->var)['type'], (string) $expr->dim->value);
    }

    /**
     * What a key reads from a holder: each arm's member, and `null` for a `null` arm, optional where a member is. Null
     * where an arm declines, or no arm is an array.
     *
     * @return ValueExpressionResult|null
     */
    private function keyRead(string $holder, string $key): ?array
    {
        $types = [];
        $optional = false;
        $nullOnly = true;

        foreach (TsTypeShape::splitTopLevel($holder, ['|']) as $arm) {
            [$member, $optionalMember] = $arm === 'null' ? ['null', false] : $this->armMember($arm, $key);

            if ($member === null) {
                return null;
            }

            $types[] = $member;
            $optional = $optional || $optionalMember;
            $nullOnly = $nullOnly && $arm === 'null';
        }

        return $nullOnly ? null : ['type' => TsTypeString::hoistNull($types), 'optional' => $optional];
    }

    /**
     * The member a key reads from an object literal or tuple arm, and whether it is optional. A null member where the
     * arm is neither, lacks the key, or its member is `unknown` or names a class.
     *
     * @return array{string|null, bool}
     */
    private function armMember(string $arm, string $key): array
    {
        [$member, $optional] = match (true) {
            TsTypeShape::isWrapped($arm, '{', '}') => $this->objectMember($arm, $key),
            TsTypeShape::isWrapped($arm, '[', ']') => $this->tupleMember($arm, $key),
            default => [null, false],
        };

        return $member === null || $member === 'unknown' || TsTypeString::shapeValueHasUnimportableToken($member)
            ? [null, false]
            : [$member, $optional];
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
     * The element a tuple holds at an index, without the `?` an optional one carries, and whether it is optional; a
     * rest element holds no single index.
     *
     * @return array{string|null, bool}
     */
    private function tupleMember(string $tuple, string $key): array
    {
        $element = preg_match('/^(?:0|[1-9]\d*)$/', $key) === 1
            ? TsTypeShape::splitTopLevel(substr($tuple, 1, -1), [','])[(int) $key] ?? null
            : null;

        if ($element === null || str_starts_with($element, '...')) {
            return [null, false];
        }

        if (! str_ends_with($element, '?')) {
            return [$element, false];
        }

        $member = trim(substr($element, 0, -1));

        return [TsTypeShape::isWrapped($member, '(', ')') ? trim(substr($member, 1, -1)) : $member, true];
    }
}

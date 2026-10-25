<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Array_ as CastArray_;
use PhpParser\Node\Expr\Cast\Bool_ as CastBool;
use PhpParser\Node\Expr\Cast\Double as CastDouble;
use PhpParser\Node\Expr\Cast\Int_ as CastInt;
use PhpParser\Node\Expr\Cast\Object_ as CastObject;
use PhpParser\Node\Expr\Cast\String_ as CastString;

/**
 * PHP cast operators: a scalar cast alone sets the type, while `(object)` and `(array)` keep what the operand holds.
 *
 * `(object)` returns an object unchanged and builds a stdClass of any other value, which an API resource's filter never
 * enters. `(array)` keeps an array, and types an object as `unknown[]`: its array form mangles non-public member keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CastHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Cast::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        return match (true) {
            $expr instanceof CastBool => ['type' => 'boolean', 'optional' => false],
            $expr instanceof CastInt, $expr instanceof CastDouble => ['type' => 'number', 'optional' => false],
            $expr instanceof CastString => ['type' => 'string', 'optional' => false],
            $expr instanceof CastObject => $this->objectCast($expr->expr, $scope, $engine),
            $expr instanceof CastArray_ => $this->arrayCast($expr->expr, $scope, $engine),
            default => null,
        };
    }

    /**
     * `(object) $operand`: an object the receiver resolver names as itself, any other value as the stdClass PHP builds
     * of each arm, null among them; null where an arm cannot be read.
     *
     * @return ValueExpressionResult|null
     */
    private function objectCast(Expr $operand, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $isObject = resolve(ReceiverClassResolver::class)->resolve($operand, $scope) !== null;
        $result = $engine->resolve($operand);
        $arms = [];

        foreach (TsTypeString::splitTopLevelUnion($result['type']) as $arm) {
            $built = match (true) {
                $arm === 'unknown' => null,
                $arm === 'null' => TsTypeStringService::EMPTY_OBJECT,
                $isObject => $arm,
                default => $this->builtObject($arm),
            };

            if ($built === null) {
                return null;
            }

            $arms[] = $built;
        }

        if ($arms === []) {
            return null;
        }

        // Two arms of an object stay apart: two same-named classes spell one token, each with its own import.
        $type = implode(' | ', $isObject ? $arms : array_values(array_unique($arms)));

        return [...$result, 'type' => $type, 'keepsKeys' => true];
    }

    /**
     * `(array) $operand`: an array literal or an operand typed as an array as itself, which an API resource's filter
     * enters again; any other value, an object the receiver resolver names among them, as `unknown[]`.
     *
     * @return ValueExpressionResult
     */
    private function arrayCast(Expr $operand, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        if ($operand instanceof Array_) {
            return $engine->resolve($operand);
        }

        if (resolve(ReceiverClassResolver::class)->resolve($operand, $scope) === null) {
            $result = $engine->resolve($operand);
            $arms = TsTypeString::splitTopLevelUnion($result['type']);

            if ($arms !== [] && array_all($arms, $this->isArray(...))) {
                unset($result['keepsKeys']);

                return $result;
            }
        }

        return ['type' => 'unknown[]', 'optional' => false];
    }

    /**
     * The stdClass `(object)` builds of one arm that holds no object: a keyed array as itself, a list or tuple keyed by
     * index, a scalar under `scalar`; null for any other arm.
     */
    private function builtObject(string $arm): ?string
    {
        if ($this->isKeyedArray($arm)) {
            return $arm;
        }

        $element = TsTypeShape::isWrapped($arm, '[', ']') ? $this->tupleValues($arm) : TsTypeShape::elementType($arm);

        if ($element !== null) {
            return 'Record<string, '.$element.'>';
        }

        return TsTypeShape::isScalar($arm) ? '{ scalar: '.$arm.' }' : null;
    }

    /**
     * Every member type of a tuple as one union, `never` for the empty tuple; null where a member cannot be read.
     */
    private function tupleValues(string $tuple): ?string
    {
        $values = [];

        foreach (array_keys(TsTypeShape::splitTopLevel(substr($tuple, 1, -1), [','])) as $index) {
            $value = TsTypeShape::memberType($tuple, (string) $index);

            if ($value === null) {
                return null;
            }

            $values[] = $value;
        }

        return $values === [] ? 'never' : TsTypeString::hoistNull($values);
    }

    /**
     * Whether one arm is an array: a list, a tuple, or a keyed array.
     */
    private function isArray(string $arm): bool
    {
        return TsTypeShape::isWrapped($arm, '[', ']') || TsTypeShape::elementType($arm) !== null || $this->isKeyedArray($arm);
    }

    /**
     * Whether one arm is a Record or an object literal, or an intersection of them.
     */
    private function isKeyedArray(string $arm): bool
    {
        return array_all(
            TsTypeShape::splitTopLevel($arm, ['&']),
            static fn (string $part): bool => TsTypeShape::members($part) !== null || TsTypeShape::recordArguments($part) !== null,
        );
    }
}

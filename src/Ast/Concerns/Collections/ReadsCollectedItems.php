<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;

/**
 * The one read of the items `collect($items)` holds and of the keys it lists, shared by KnownFunctionCallHandler's
 * `collect()` and `array_keys()` and CollectionPipelineHandler's root.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ReadsCollectedItems
{
    /**
     * The array `collect($items)` holds: null as no items, a constant array typed as a parameter default is, and an
     * expression typed as one list, tuple, Record or object shape as itself, the empty tuple as the empty list, and a
     * `null` arm or a `when*()` value as no items; a value nothing types as `unknown`. Null for any other value.
     *
     * @return ValueExpressionResult|null
     */
    protected function collectedItems(Expr $items, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $resolver = new ValueResolver;

        try {
            $value = $resolver->evaluateConstantExpression($items, $scope);

            // Arr::wrap() turns null into no items, and a scalar into one, which no caller writes.
            return match (true) {
                $value === null => ['type' => 'never[]', 'optional' => false],
                is_array($value) => $resolver->resolveConstantValue($value, $scope->casesAsLiterals),
                default => null,
            };
        } catch (ConstExprEvaluationException) {
        }

        $result = $engine->resolve($items);
        $type = $result['type'];

        if ($type === 'unknown') {
            return [...$result, 'optional' => false];
        }

        $arms = TsTypeString::splitTopLevelUnion($type);
        $items = array_values(array_diff($arms, ['null']));

        if (count($items) > 1) {
            return null;
        }

        // The empty tuple, such as a `list{}` return, holds no element: it is the empty list a literal `[]` prints.
        $type = $items === [] || $items[0] === '[]' ? 'never[]' : $items[0];
        $listed = str_ends_with($type, '[]');

        if (! $listed && ! str_starts_with($type, '{') && TsTypeShape::recordArguments($type) === null) {
            return null;
        }

        // collect(null), and collect() of the MissingValue a when*() call leaves, hold no items, which json_encode()
        // writes as `[]`: a list admits that, a Record or a shape does not.
        $empties = count($arms) > 1 || $result['optional'];

        return [...$result, 'type' => $listed || ! $empties ? $type : $type.' | never[]', 'optional' => false];
    }

    /**
     * The list `array_keys($items)` or `collect($items)->keys()` makes: the keys a constant array or a literal holds
     * where it names each one, else the keys $result's type names as ArrayArms::keysList() reads them, but for a
     * literal holding a key its type leaves out; null where none can be read.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult|null
     */
    protected function collectedKeys(?Expr $items, array $result, AnalysisScope $scope): ?array
    {
        $keys = $items === null ? null : $this->heldKeys($items, $scope);
        $list = match (true) {
            $keys !== null => ArrayArms::keysListOf($keys),
            $result['unnamedKeys'] ?? false => null,
            default => ArrayArms::keysList($result['type']),
        };

        return $list === null ? null : ['type' => $list, 'optional' => false];
    }

    /**
     * The keys an array expression holds, read without its type: a constant array's, or a literal's as
     * ValueResolver::literalKeys() reads them; null for any other expression.
     *
     * @return list<int|string>|null
     */
    private function heldKeys(Expr $items, AnalysisScope $scope): ?array
    {
        $resolver = new ValueResolver;

        try {
            $value = $resolver->evaluateConstantExpression($items, $scope);

            return is_array($value) ? array_keys($value) : null;
        } catch (ConstExprEvaluationException) {
        }

        return $items instanceof Array_ ? $resolver->literalKeys($items, $scope) : null;
    }
}

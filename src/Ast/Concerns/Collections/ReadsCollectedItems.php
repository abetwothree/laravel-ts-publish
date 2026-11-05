<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;

/**
 * The one read of the items `collect($items)` holds, shared by KnownFunctionCallHandler's `collect()` and
 * CollectionPipelineHandler's root.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ReadsCollectedItems
{
    /**
     * The array `collect($items)` holds: null as no items, a constant array typed as a parameter default is, and an
     * expression typed as one list, tuple, Record or object shape as itself, the empty tuple as the empty list and a
     * `null` arm as no items; a value nothing types as `unknown`. Null for any other value.
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

        // collect(null) holds no items, which json_encode() writes as `[]`: a list admits that, a Record or shape not.
        return [...$result, 'type' => $listed || count($arms) === 1 ? $type : $type.' | never[]', 'optional' => false];
    }
}

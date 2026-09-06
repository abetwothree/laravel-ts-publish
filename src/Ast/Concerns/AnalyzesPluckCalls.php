<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * Type a `pluck('field')` call against the ambient whenLoaded closure's related model.
 *
 * The single home for this: RelationCollectionChainHandler reaches it through a relation chain,
 * VariableHandler through a bound variable. Requires the host to also use ResolvesRelatedModelTypes.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait AnalyzesPluckCalls
{
    /**
     * Analyze a `$variable->pluck('field')` call within a whenLoaded closure context.
     *
     * Returns `unknown[]`, not `unknown`, when the field type cannot be determined — callers that
     * only test for a non-`unknown` result rely on that.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeVariablePluckCall(MethodCall $call, AnalysisScope $scope): array
    {
        $field = CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'pluck'))->named('value')?->value;

        if ($field instanceof String_) {
            $info = $this->analyzeRelatedModelProperty($field->value, $scope);

            if ($info['type'] !== 'unknown') {
                $info['type'] = ValueResult::arrayWrapType($info['type']);
                $info['optional'] = false;

                return $info;
            }
        }

        return ['type' => 'unknown[]', 'optional' => false];
    }
}

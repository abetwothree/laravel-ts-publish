<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * Type a `pluck('field')` call against the element model of the collection it is called on.
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
     * Analyze a `pluck('field')` call on a collection of $modelFqcn models.
     *
     * Returns `unknown[]`, not `unknown`, when the field type cannot be determined — callers that
     * only test for a non-`unknown` result rely on that.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult
     */
    protected function analyzeVariablePluckCall(MethodCall $call, AnalysisScope $scope, string $modelFqcn): array
    {
        $field = CallArguments::for($call, new ReflectionMethod(EloquentCollection::class, 'pluck'))->named('value')?->value;

        if ($field instanceof String_) {
            $info = $this->analyzeRelatedModelProperty($field->value, $scope, $modelFqcn);

            if ($info['type'] !== 'unknown') {
                $info['type'] = ValueResult::arrayWrapType($info['type']);
                $info['optional'] = false;

                return $info;
            }
        }

        return ['type' => 'unknown[]', 'optional' => false];
    }
}

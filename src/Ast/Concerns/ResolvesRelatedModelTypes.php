<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolve a property/method access against a related model — an explicitly bound one, or by
 * default the ambient whenLoaded closure's related model.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ResolvesRelatedModelTypes
{
    /**
     * Resolve a property access on a related model — an explicitly bound model, or by default the
     * ambient whenLoaded closure's related model.
     *
     * Uses the same resolution chain as model attributes: accessor → cast → DB column type.
     *
     * @param  class-string<Model>|null  $modelFqcn
     * @return ValueExpressionResult
     */
    protected function analyzeRelatedModelProperty(string $propertyName, AnalysisScope $scope, ?string $modelFqcn = null): array
    {
        $modelFqcn ??= $scope->closureRelationModelClass;

        if ($modelFqcn === null) {
            return ValueResult::unknown(); // @codeCoverageIgnore
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($modelFqcn, $propertyName);

        if ($tsInfo['type'] === 'unknown') {
            return ValueResult::unknown();
        }

        $info = ['type' => $tsInfo['type'], 'optional' => false];

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        if ($enumFqcn !== null) {
            $info['directEnumFqcn'] = $enumFqcn;
        }

        return $info;
    }

    /**
     * Resolve a method call (instance or static) on a related model — an explicitly bound model, or
     * by default the ambient whenLoaded closure's related model.
     *
     * Accepted only when its tokens can be imported; see ReflectedTypeAcceptor::accept().
     *
     * @param  class-string<Model>|null  $modelFqcn
     * @return ValueExpressionResult
     */
    protected function analyzeRelatedModelMethodCall(string $methodName, AnalysisScope $scope, ?string $modelFqcn = null): array
    {
        $modelFqcn ??= $scope->closureRelationModelClass;

        if ($modelFqcn === null) {
            return ValueResult::unknown(); // @codeCoverageIgnore
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveMethodReturnType($modelFqcn, $methodName);

        return resolve(ReflectedTypeAcceptor::class)->accept($tsInfo) ?? ValueResult::unknown();
    }
}

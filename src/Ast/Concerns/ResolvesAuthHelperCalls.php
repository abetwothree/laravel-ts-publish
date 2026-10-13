<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;

/**
 * The `user()`/`id()` result shared by the two auth entry points, `auth()->…` and `Auth::…`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
trait ResolvesAuthHelperCalls
{
    /**
     * Type `user()` or `id()` against the resolved auth model, or decline for any other name.
     *
     * @param  class-string<Model>|null  $model
     * @return ValueExpressionResult|null
     */
    protected function authMethodResult(string $method, ?string $model): ?array
    {
        if ($model === null) {
            return null;
        }

        if ($method === 'user') {
            return ['type' => class_basename($model).' | null', 'optional' => false, 'modelFqcn' => $model];
        }

        if ($method !== 'id') {
            return null;
        }

        DependencyRecorder::recordClass($model);

        $keyType = $this->identifiesByKey($model) ? resolve(ModelAttributeResolver::class)->keyTsType($model) : null;

        return $keyType === null ? null : ['type' => ValueResult::withNullArm($keyType), 'optional' => false];
    }

    /**
     * Whether Auth::id() sends the model's key, which it does not once the model names another identifier column.
     *
     * @param  class-string<Model>  $model
     */
    private function identifiesByKey(string $model): bool
    {
        $framework = trait_exists(Authenticatable::class) ? new ReflectionClass(Authenticatable::class)->getFileName() : false;

        foreach (['getAuthIdentifier', 'getAuthIdentifierName'] as $method) {
            if (method_exists($model, $method) && new ReflectionMethod($model, $method)->getFileName() !== $framework) {
                return false;
            }
        }

        return true;
    }
}

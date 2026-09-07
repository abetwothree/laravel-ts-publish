<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;

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

        $instance = resolve(ModelAttributeResolver::class)->getInstance($model);

        return ['type' => ($instance?->getKeyType() === 'int' ? 'number' : 'string').' | null', 'optional' => false];
    }
}

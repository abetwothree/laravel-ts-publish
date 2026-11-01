<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Resolves the Eloquent model behind the application's default auth guard.
 *
 * The package runs inside the booted app, so the live guard → provider → model chain is read
 * directly instead of guessing at `App\Models\User`.
 *
 * @internal
 */
final class AuthUserResolver
{
    /**
     * The default guard's user-provider model, or null when it is not an Eloquent model.
     *
     * @return class-string<Model>|null
     */
    public function model(): ?string
    {
        $guard = Config::get('auth.defaults.guard');

        return is_string($guard) ? $this->guardModel($guard) : null;
    }

    /**
     * The default guard's model where no configured guard's provider names another Eloquent model, else null.
     *
     * A route's `auth:<guard>` middleware makes its guard the default for that request, so any guard's model may be
     * the one signed in.
     *
     * @return class-string<Model>|null
     */
    public function soleModel(): ?string
    {
        $model = $this->model();
        $models = $model === null ? [] : resolve(AnalysisMemo::class)->remember('auth-guard-models', $this->guardModels(...));

        return $models === [$model] ? $model : null;
    }

    /**
     * Each Eloquent model a configured guard's provider names, once; a provider naming none, as Sanctum's, is skipped.
     *
     * @return list<class-string<Model>>
     */
    private function guardModels(): array
    {
        $guards = Config::get('auth.guards');
        $models = [];

        foreach (is_array($guards) ? array_keys($guards) : [] as $guard) {
            $model = $this->guardModel((string) $guard);

            if ($model !== null) {
                $models[$model] = $model;
            }
        }

        return array_values($models);
    }

    /**
     * A guard's user-provider model, or null when it is not an Eloquent model.
     *
     * @return class-string<Model>|null
     */
    private function guardModel(string $guard): ?string
    {
        $provider = Config::get("auth.guards.{$guard}.provider");

        if (! is_string($provider)) {
            return null;
        }

        $model = Config::get("auth.providers.{$provider}.model");

        if (! is_string($model) || ! class_exists($model) || ! is_a($model, Model::class, true)) {
            return null;
        }

        return $model;
    }
}

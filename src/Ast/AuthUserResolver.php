<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

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

        if (! is_string($guard)) {
            return null;
        }

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

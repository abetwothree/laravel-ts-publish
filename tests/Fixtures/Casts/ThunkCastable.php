<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** A Castable value whose override is an arrow function, cast by an anonymous caster that passes a NULL through. */
#[TsType('() => string')]
final class ThunkCastable implements Castable
{
    /**
     * Builds an anonymous caster that reads a stored value as a thunk.
     *
     * @param  array<int, string>  $arguments
     * @return CastsAttributes<self|null, string>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /** @param  array<string, mixed>  $attributes */
            public function get(Model $model, string $key, mixed $value, array $attributes): ?ThunkCastable
            {
                return $value === null ? null : new ThunkCastable;
            }

            /** @param  array<string, mixed>  $attributes */
            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                return $value === null ? null : 'thunk';
            }
        };
    }
}

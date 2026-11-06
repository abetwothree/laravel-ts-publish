<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** A Castable value whose caster is an anonymous class, which only a call to castUsing() could read. */
final class AnonymousCasterCastable implements Castable
{
    public function __construct(public string $label = '') {}

    /**
     * Builds an anonymous caster that reads every value, a NULL too, as a label.
     *
     * @param  array<int, string>  $arguments
     * @return CastsAttributes<self, string>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /** @param  array<string, mixed>  $attributes */
            public function get(Model $model, string $key, mixed $value, array $attributes): AnonymousCasterCastable
            {
                return new AnonymousCasterCastable((string) $value);
            }

            /** @param  array<string, mixed>  $attributes */
            public function set(Model $model, string $key, mixed $value, array $attributes): string
            {
                return $value instanceof AnonymousCasterCastable ? $value->label : (string) $value;
            }
        };
    }
}

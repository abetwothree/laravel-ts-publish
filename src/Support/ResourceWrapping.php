<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionProperty;

/**
 * The key an API resource response wraps its body in, read from the live `$wrap` static.
 *
 * `ts:publish` runs in the booted app, so a service provider's `withoutWrapping()` or `wrap()` has already run. Every
 * class that does not redeclare `$wrap` shares `JsonResource`'s slot; a redeclaring class owns its subtree's.
 *
 * @internal
 */
final class ResourceWrapping
{
    /**
     * The key the response of a class Laravel instantiates wraps its body in, or null when it sends the body bare.
     *
     * @param  class-string<JsonResource>  $class
     */
    public static function key(string $class): ?string
    {
        $wrap = $class::$wrap;

        return is_string($wrap) && $wrap !== '' ? $wrap : null;
    }

    /**
     * Each loaded class owning a `$wrap` slot whose live value differs from its declared default, for the cache
     * fingerprint. A class a provider re-wraps is loaded by that call, so the loaded classes are the complete set.
     *
     * @return array<class-string, string|null> class => live `$wrap`, sorted by class
     */
    public static function runtimeOverrides(): array
    {
        $overrides = [];

        foreach (get_declared_classes() as $class) {
            if (! is_a($class, JsonResource::class, true)) {
                continue;
            }

            $property = new ReflectionProperty($class, 'wrap');

            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $live = $property->getValue();

            if ($live !== $property->getDefaultValue()) {
                $overrides[$class] = is_string($live) ? $live : null;
            }
        }

        ksort($overrides);

        return $overrides;
    }
}

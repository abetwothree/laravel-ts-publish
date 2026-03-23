<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use ReflectionClass;

/**
 * Parses #[TsCasts] attributes from a class, $casts property, and casts() method,
 * merging them with Laravel's own cast resolution priority: class < property < method.
 *
 * @phpstan-type TsCastsResult = array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 * }
 */
trait ParsesTsCasts
{
    /**
     * Parse #[TsCasts] attributes from all three locations and return merged overrides.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @return TsCastsResult
     */
    protected function parseTsCastsFromReflection(ReflectionClass $reflection): array
    {
        $classOverrides = [];
        $propertyOverrides = [];
        $methodOverrides = [];

        // Class-level (Laravel 13+ style, or when there is no $casts property/method)
        foreach ($reflection->getAttributes(TsCasts::class) as $attr) {
            $classOverrides = array_merge($classOverrides, $attr->newInstance()->types);
        }

        // $casts property (older style)
        if ($reflection->hasProperty('casts')) {
            foreach ($reflection->getProperty('casts')->getAttributes(TsCasts::class) as $attr) {
                $propertyOverrides = array_merge($propertyOverrides, $attr->newInstance()->types);
            }
        }

        // casts() method (Laravel 9+ style)
        if ($reflection->hasMethod('casts')) {
            foreach ($reflection->getMethod('casts')->getAttributes(TsCasts::class) as $attr) {
                $methodOverrides = array_merge($methodOverrides, $attr->newInstance()->types);
            }
        }

        // Method wins over property wins over class, matching Laravel's own cast resolution
        $merged = array_merge($classOverrides, $propertyOverrides, $methodOverrides);

        $overrides = [];
        $importPaths = [];

        foreach ($merged as $column => $value) {
            if (is_array($value)) {
                /** @var array{type: string, import: string} $value */
                $overrides[$column] = $value['type'];
                $importPaths[$column] = $value['import'];
            } else {
                $overrides[$column] = $value;
            }
        }

        return ['overrides' => $overrides, 'importPaths' => $importPaths];
    }
}

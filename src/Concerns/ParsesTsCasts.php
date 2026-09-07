<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use ReflectionClass;

/**
 * Parses #[TsCasts] attributes from a class, $casts property, and casts() method,
 * merging them with Laravel's own cast resolution priority: class < property < method.
 *
 * @phpstan-import-type TsCastsUnpacked from TsCastsReader
 *
 * @phpstan-type TsCastsResult = array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 *     optionalOverrides: array<string, bool>,
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
        $attributes = [];

        // Class-level (Laravel 13+ style, or when there is no $casts property/method)
        foreach ($reflection->getAttributes(TsCasts::class) as $attr) {
            $attributes[] = $attr->newInstance();
        }

        // $casts property (older style)
        if ($reflection->hasProperty('casts')) {
            foreach ($reflection->getProperty('casts')->getAttributes(TsCasts::class) as $attr) {
                $attributes[] = $attr->newInstance();
            }
        }

        // casts() method (Laravel 9+ style)
        if ($reflection->hasMethod('casts')) {
            foreach ($reflection->getMethod('casts')->getAttributes(TsCasts::class) as $attr) {
                $attributes[] = $attr->newInstance();
            }
        }

        /** @var TsCastsUnpacked $unpacked */
        $unpacked = resolve(TsCastsReader::class)->unpack($attributes);

        return [
            'overrides' => $unpacked['overrides'],
            'importPaths' => $unpacked['importPaths'],
            'optionalOverrides' => $unpacked['optionalOverrides'],
        ];
    }

    /**
     * Normalize TsCasts definitions into types, imports, and optional flags.
     *
     * @param  array<string, string|array{type: string, import?: string, optional?: bool}>  $types
     * @return TsCastsResult
     */
    protected function normalizeTsCasts(array $types): array
    {
        /** @var TsCastsUnpacked $unpacked */
        $unpacked = resolve(TsCastsReader::class)->unpack([new TsCasts($types)]);

        return [
            'overrides' => $unpacked['overrides'],
            'importPaths' => $unpacked['importPaths'],
            'optionalOverrides' => $unpacked['optionalOverrides'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Support\Facades\Config;
use ReflectionClass;

/**
 * Finds the composed classes in the configured directories, so their modules are written before any value uses them.
 *
 * @extends CoreCollector<object>
 */
class ClassTypesCollector extends CoreCollector
{
    /**
     * None: a composed class can live anywhere, so only the configured directories are scanned.
     */
    protected function defaultDirectory(): string
    {
        return '';
    }

    /** @param ReflectionClass<object> $reflection */
    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $this->validateClassType($reflection);
    }

    protected function finderSettings(): array
    {
        return [
            'included' => $this->sanitizeAllowSetting(Config::array('ts-publish.class_types.included', [])),
            'excluded' => $this->sanitizeAllowSetting(Config::array('ts-publish.class_types.excluded', [])),
            'additional_directories' => $this->sanitizeAllowSetting(Config::array('ts-publish.class_types.additional_directories', [])),
        ];
    }
}

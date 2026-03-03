<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use BackedEnum;
use ReflectionClass;
use UnitEnum;

/**
 * @extends CoreCollector<UnitEnum|BackedEnum>
 */
class EnumsCollector extends CoreCollector
{
    protected function defaultDirectory(): string
    {
        return app_path('Enums');
    }

    /** @param ReflectionClass<object> $reflection */
    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $reflection->isEnum();
    }

    protected function finderSettings(): array
    {
        return [
            'included' => array_values(array_filter(config()->array('ts-publish.included_enums'), 'is_string')),
            'excluded' => array_values(array_filter(config()->array('ts-publish.excluded_enums'), 'is_string')),
            'additional_directories' => array_values(array_filter(config()->array('ts-publish.additional_enum_directories'), 'is_string')),
        ];
    }
}

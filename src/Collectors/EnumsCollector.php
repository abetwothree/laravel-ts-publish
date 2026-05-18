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
        return $this->validateEnum($reflection);
    }

    protected function finderSettings(): array
    {
        return [
            'included' => $this->sanitizeAllowSetting(config()->array('ts-publish.enums.included')),
            'excluded' => $this->sanitizeAllowSetting(config()->array('ts-publish.enums.excluded')),
            'additional_directories' => $this->sanitizeAllowSetting(config()->array('ts-publish.enums.additional_directories')),
        ];
    }
}

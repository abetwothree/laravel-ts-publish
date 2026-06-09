<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use ReflectionClass;

/**
 * @extends CoreCollector<Model>
 */
class ModelsCollector extends CoreCollector
{
    protected function defaultDirectory(): string
    {
        return app_path('Models');
    }

    /** @param ReflectionClass<object> $reflection */
    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $this->validateModel($reflection);
    }

    protected function finderSettings(): array
    {
        return [
            'included' => $this->sanitizeAllowSetting(Config::array('ts-publish.models.included')),
            'excluded' => $this->sanitizeAllowSetting(Config::array('ts-publish.models.excluded')),
            'additional_directories' => $this->sanitizeAllowSetting(Config::array('ts-publish.models.additional_directories')),
        ];
    }
}

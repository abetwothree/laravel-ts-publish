<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Database\Eloquent\Model;
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
            'included' => $this->sanitizeAllowSetting(config()->array('ts-publish.included_models')),
            'excluded' => $this->sanitizeAllowSetting(config()->array('ts-publish.excluded_models')),
            'additional_directories' => $this->sanitizeAllowSetting(config()->array('ts-publish.additional_model_directories')),
        ];
    }
}

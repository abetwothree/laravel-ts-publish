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
        return $reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract();
    }

    protected function finderSettings(): array
    {
        return [
            'included' => array_values(array_filter(config()->array('ts-publish.included_models'), 'is_string')),
            'excluded' => array_values(array_filter(config()->array('ts-publish.excluded_models'), 'is_string')),
            'additional_directories' => array_values(array_filter(config()->array('ts-publish.additional_model_directories'), 'is_string')),
        ];
    }
}

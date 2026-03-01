<?php

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

    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract();
    }

    protected function finderSettings(): array
    {
        return [
            'included' => config()->array('ts-publish.included_models'),
            'excluded' => config()->array('ts-publish.excluded_models'),
            'additional_directories' => config()->array('ts-publish.additional_model_directories'),
        ];
    }
}

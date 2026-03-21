<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

/**
 * @extends CoreCollector<JsonResource>
 */
class ResourcesCollector extends CoreCollector
{
    protected function defaultDirectory(): string
    {
        return app_path('Http/Resources');
    }

    /** @param ReflectionClass<object> $reflection */
    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $reflection->isSubclassOf(JsonResource::class)
            && ! $reflection->isAbstract()
            && ! $reflection->isSubclassOf(ResourceCollection::class)
            && $reflection->getName() !== EnumResource::class;
    }

    protected function finderSettings(): array
    {
        return [
            'included' => $this->sanitizeAllowSetting(config()->array('ts-publish.included_resources')),
            'excluded' => $this->sanitizeAllowSetting(config()->array('ts-publish.excluded_resources')),
            'additional_directories' => $this->sanitizeAllowSetting(config()->array('ts-publish.additional_resource_directories')),
        ];
    }
}

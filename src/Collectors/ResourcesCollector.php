<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Http\Resources\Json\JsonResource;
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
        return $this->validateResource($reflection);
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

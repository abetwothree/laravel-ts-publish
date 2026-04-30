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
            'included' => $this->sanitizeAllowSetting(config()->array('ts-publish.resources.included')),
            'excluded' => $this->sanitizeAllowSetting(config()->array('ts-publish.resources.excluded')),
            'additional_directories' => $this->sanitizeAllowSetting(config()->array('ts-publish.resources.additional_directories')),
        ];
    }
}

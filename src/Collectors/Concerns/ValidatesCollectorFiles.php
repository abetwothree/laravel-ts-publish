<?php

namespace AbeTwoThree\LaravelTsPublish\Collectors\Concerns;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

trait ValidatesCollectorFiles
{
    /** @param ReflectionClass<object> $reflection */
    protected function validateEnum(ReflectionClass $reflection): bool
    {
        return $reflection->isEnum();
    }

    /** @param ReflectionClass<object> $reflection */
    protected function validateModel(ReflectionClass $reflection): bool
    {
        return $reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract();
    }

    /** @param ReflectionClass<covariant object> $reflection */
    protected function validateResource(ReflectionClass $reflection): bool
    {
        return $reflection->isSubclassOf(JsonResource::class)
            && ! $reflection->isAbstract()
            && ! $reflection->isSubclassOf(ResourceCollection::class)
            && $reflection->getName() !== EnumResource::class;
    }
}

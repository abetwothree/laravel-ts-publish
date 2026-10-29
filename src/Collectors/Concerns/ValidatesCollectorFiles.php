<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use AbeTwoThree\LaravelTsPublish\Support\RoutedControllers;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

trait ValidatesCollectorFiles
{
    /** @param ReflectionClass<object> $reflection */
    protected function validateEnum(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection)) {
            return false;
        }

        return $reflection->isEnum();
    }

    /** @param ReflectionClass<object> $reflection */
    protected function validateModel(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection)) {
            return false;
        }

        return $reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract();
    }

    /** @param ReflectionClass<covariant object> $reflection */
    protected function validateResource(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection)) {
            return false;
        }

        return $reflection->isSubclassOf(JsonResource::class)
            && ! $reflection->isAbstract()
            && $reflection->getName() !== EnumResource::class;
    }

    /** @param ReflectionClass<object> $reflection */
    protected function validateController(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection)) {
            return false;
        }

        // Must be a concrete, non-abstract class
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
            return false;
        }

        return RoutedControllers::dispatchesTo($reflection->getName());
    }

    /** @param ReflectionClass<covariant object> $reflection */
    protected function excluded(ReflectionClass $reflection): bool
    {
        return $reflection->getAttributes(TsExclude::class) !== [];
    }

    /** @param ReflectionClass<object> $reflection */
    protected function validateFormRequest(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection)) {
            return false;
        }

        return $reflection->isSubclassOf(FormRequest::class) && ! $reflection->isAbstract();
    }

    /**
     * Whether a class's or interface's override composes, so it publishes a module of its own. resolve() already keeps
     * an owned or opted-out class from composing, so neither is checked twice.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function validateClassType(ReflectionClass $reflection): bool
    {
        return ClassTypeOverride::resolve($reflection->getName())['composed'] ?? false;
    }

    /** @param ReflectionClass<object> $reflection */
    protected function validateBroadcastEvent(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection)) {
            return false;
        }

        return ($reflection->implementsInterface(ShouldBroadcast::class) || $reflection->implementsInterface(ShouldBroadcastNow::class))
            && ! $reflection->isAbstract();
    }
}

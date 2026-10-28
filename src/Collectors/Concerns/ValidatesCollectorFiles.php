<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Router;
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
            return false; // @codeCoverageIgnore
        }

        // Must have at least one route registered for this controller
        /** @var Router $router */
        $router = app(Router::class);

        $fqcn = $reflection->getName();

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (ltrim((string) $route->getControllerClass(), '\\') === $fqcn) {
                return true;
            }
        }

        return false;
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
     * Whether a class's override composes, so it publishes a module of its own. A class another publisher owns never
     * composes, so its own file is never taken.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function validateClassType(ReflectionClass $reflection): bool
    {
        if ($this->excluded($reflection) || $reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum()) {
            return false;
        }

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

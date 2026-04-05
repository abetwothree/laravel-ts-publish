<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Model;
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
            return false;
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
}

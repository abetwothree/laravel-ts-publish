<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use ReflectionClass;

class RoutesCollector
{
    public function __construct(
        protected Router $router,
    ) {}

    /**
     * Collect all controller FQCNs that have at least one publishable route.
     *
     * Skips:
     * - Routes with names starting with 'generated::' (cache artifacts)
     * - Fallback routes
     * - Routes without a controller (closure-only routes)
     * - Controllers whose class carries a #[TsExclude] attribute
     *
     * @return Collection<int, class-string>
     */
    public function collect(): Collection
    {
        $only = array_values(array_filter(config()->array('ts-publish.routes.only', []), 'is_string'));
        $except = array_values(array_filter(config()->array('ts-publish.routes.except', []), 'is_string'));
        $onlyNamed = config()->boolean('ts-publish.routes.only_named', false);
        $excludeMiddleware = array_values(array_filter(config()->array('ts-publish.routes.exclude_middleware', []), 'is_string'));

        /** @var Collection<int, class-string> $controllers */
        $controllers = collect($this->router->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => $this->shouldIncludeRoute($route, $only, $except, $onlyNamed, $excludeMiddleware))
            ->map(fn (Route $route) => ltrim((string) $route->getControllerClass(), '\\'))
            ->filter(fn (string $class) => class_exists($class))
            ->unique()
            ->values()
            ->filter(fn (string $class) => $this->shouldIncludeController($class));

        /** @var Collection<int, class-string> */
        return $controllers->values();
    }

    /**
     * Determine whether a route should be included in publishing.
     *
     * @param  list<string>  $only
     * @param  list<string>  $except
     * @param  list<string>  $excludeMiddleware
     */
    protected function shouldIncludeRoute(Route $route, array $only, array $except, bool $onlyNamed, array $excludeMiddleware): bool
    {
        // Skip Laravel route-cache artifacts
        $name = $route->getName();

        if ($name !== null && str_starts_with($name, 'generated::')) {
            return false;
        }

        // Skip fallback routes
        if ($route->isFallback) {
            return false;
        }

        // Skip closure-only routes (no controller to group under)
        if ($route->getControllerClass() === null) {
            return false;
        }

        // When only_named is true, skip unnamed routes
        if ($onlyNamed && $name === null) {
            return false;
        }

        // Skip routes behind excluded middleware
        if ($excludeMiddleware !== []) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && in_array($mw, $excludeMiddleware, true)) {
                    return false;
                }
            }
        }

        // Apply 'only' patterns (include only matching routes)
        if ($only !== []) {
            return $name !== null && $this->matchesPatterns($name, $only);
        }

        // Apply 'except' patterns (exclude matching routes)
        if ($except !== [] && $name !== null && $this->matchesPatterns($name, $except)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether a controller class should be included.
     * Checks for #[TsExclude] on the controller class.
     *
     * @param  class-string  $class
     */
    protected function shouldIncludeController(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return $reflection->getAttributes(TsExclude::class) === [];
    }

    /**
     * Check whether a route name matches any of the given patterns.
     * Supports wildcards ('posts.*') and negation ('!posts.index').
     *
     * @param  list<string>  $patterns
     */
    protected function matchesPatterns(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $negated = str_starts_with($pattern, '!');
            $actualPattern = $negated ? substr($pattern, 1) : $pattern;
            $matches = fnmatch($actualPattern, $name);

            if ($negated && $matches) {
                return false;
            }

            if (! $negated && $matches) {
                return true;
            }
        }

        return false;
    }
}

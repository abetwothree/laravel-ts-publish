<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use Illuminate\Routing\Route;

trait FiltersRoutes
{
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

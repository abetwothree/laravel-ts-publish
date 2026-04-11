<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use AbeTwoThree\LaravelTsPublish\Collectors\Concerns\ValidatesCollectorFiles;
use AbeTwoThree\LaravelTsPublish\Concerns\FiltersRoutes;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use ReflectionClass;

class RoutesCollector
{
    use FiltersRoutes;
    use ValidatesCollectorFiles;

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
     * Determine whether a controller class should be included.
     *
     * @param  class-string  $class
     */
    protected function shouldIncludeController(string $class): bool
    {
        if (! class_exists($class)) {
            return false; // @codeCoverageIgnore
        }

        $reflection = new ReflectionClass($class);

        return $this->validateController($reflection);
    }
}

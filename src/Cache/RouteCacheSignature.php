<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

class RouteCacheSignature
{
    /**
     * Build a deterministic signature of every route mapped to a controller.
     *
     * Route definitions (URI, HTTP methods, name, domain, action method, and
     * middleware) live in route files — not in the controller class file — so
     * they are invisible to the file-content fingerprint. Folding this signature
     * into the fingerprint makes a route change bust exactly the controllers
     * whose routes changed. Returns '' when the controller has no routes.
     */
    public static function for(string $controllerClass): string
    {
        /** @var Router $router */
        $router = app(Router::class);

        $signatures = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (ltrim((string) $route->getControllerClass(), '\\') !== $controllerClass) {
                continue;
            }

            $methods = array_values(array_filter($route->methods(), 'is_string'));
            sort($methods);

            $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
            sort($middleware);

            $signatures[] = implode('|', [
                (string) $route->getName(),
                $route->uri(),
                implode(',', $methods),
                (string) $route->getDomain(),
                $route->getActionMethod(),
                implode(',', $middleware),
            ]);
        }

        sort($signatures);

        return $signatures === [] ? '' : hash('xxh128', implode("\n", $signatures));
    }
}

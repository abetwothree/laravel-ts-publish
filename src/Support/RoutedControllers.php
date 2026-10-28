<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Routing\Router;

/**
 * The classes a registered route dispatches to, read from the router once per run.
 *
 * @internal
 */
final class RoutedControllers
{
    /** @var array<string, true>|null Lower-cased, as PHP reads a class name in any case. */
    private static ?array $controllers = null;

    /**
     * Whether a registered route dispatches to a class, however either spells its name.
     */
    public static function has(string $class): bool
    {
        return isset(self::controllers()[strtolower(ltrim($class, '\\'))]);
    }

    /**
     * Forget the routes read, at the start of a run.
     */
    public static function flush(): void
    {
        self::$controllers = null;
    }

    /**
     * Each routed class's lower-cased name, read on first use.
     *
     * @return array<string, true>
     */
    private static function controllers(): array
    {
        if (self::$controllers !== null) {
            return self::$controllers;
        }

        /** @var Router $router */
        $router = resolve(Router::class);
        $controllers = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $class = $route->getControllerClass();

            if ($class !== null) {
                $controllers[strtolower(ltrim($class, '\\'))] = true;
            }
        }

        return self::$controllers = $controllers;
    }
}

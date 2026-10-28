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
    /** @var array<string, true>|null Each routed class's name as its route spells it. */
    private static ?array $exact = null;

    /** @var array<string, true> The same names lower-cased, as PHP reads a class name in any case. */
    private static array $anyCase = [];

    /**
     * Whether a registered route dispatches to a class, however either spells its name: the routes publisher owns
     * such a class's file.
     */
    public static function has(string $class): bool
    {
        self::read();

        return isset(self::$anyCase[strtolower(ltrim($class, '\\'))]);
    }

    /**
     * Whether a registered route names the class exactly, as the routes publisher matches a controller's actions.
     */
    public static function dispatchesTo(string $class): bool
    {
        self::read();

        return isset(self::$exact[ltrim($class, '\\')]);
    }

    /**
     * Forget the routes read, at the start of a run.
     */
    public static function flush(): void
    {
        self::$exact = null;
        self::$anyCase = [];
    }

    /**
     * Read each routed class's name from the router, unless this run already has.
     */
    private static function read(): void
    {
        if (self::$exact !== null) {
            return;
        }

        /** @var Router $router */
        $router = resolve(Router::class);
        $exact = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $class = $route->getControllerClass();

            if ($class !== null) {
                $exact[ltrim($class, '\\')] = true;
            }
        }

        self::$exact = $exact;
        self::$anyCase = array_change_key_case($exact);
    }
}

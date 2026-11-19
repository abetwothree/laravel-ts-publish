<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use PropertyHookType;
use ReflectionClass;

/**
 * Reads what a PHP 8.4 property hook does to a read of its property.
 *
 * @internal
 */
final class PropertyHooks
{
    /**
     * Whether the class declares or inherits the property with a get hook, which runs on every read of it.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function runsGetHook(ReflectionClass $class, string $property): bool
    {
        return $class->hasProperty($property) && $class->getProperty($property)->hasHook(PropertyHookType::Get);
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node\Name;
use ReflectionFunction;

/**
 * The function a call's name runs, found as PHP finds it.
 *
 * @internal
 */
final class NamedFunction
{
    /**
     * The function a name calls, tried in its namespace first, then globally, as PHP does.
     *
     * A reading takes what it learns of the function from its declaration, so a user function's file is recorded.
     */
    public static function resolve(Name $name): ?ReflectionFunction
    {
        $namespaced = $name->getAttribute('namespacedName');

        foreach ([$namespaced instanceof Name ? $namespaced->toString() : null, $name->toString()] as $candidate) {
            if ($candidate !== null && function_exists($candidate)) {
                $function = new ReflectionFunction($candidate);
                $file = $function->getFileName();

                if ($file !== false) {
                    DependencyRecorder::record($file);
                }

                return $function;
            }
        }

        return null;
    }
}

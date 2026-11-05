<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Receivers;

/**
 * The lattice an `instanceof` test narrows classes by: what each class a value holds becomes once the test passes,
 * and what two proofs of one value leave together. ReceiverClassResolver narrows a receiver's classes through it.
 *
 * @internal
 */
final class InstanceofNarrowing
{
    /**
     * What some classes become under `instanceof` tests, each narrowed class by class; none where no class can pass.
     *
     * @param  list<class-string>  $classes
     * @param  non-empty-list<class-string>  $tested
     * @return list<class-string>
     */
    public static function classes(array $classes, array $tested): array
    {
        $narrowed = [];

        foreach ($classes as $class) {
            $narrowed = [...$narrowed, ...self::narrowedClass($class, $tested)];
        }

        return array_values(array_unique($narrowed));
    }

    /**
     * The classes a value proven to hold one of $held holds once a test for $tested passes too: each narrowed class by
     * class, or the tested classes where none can pass, as a test on a variable a closure rewrote may find.
     *
     * @param  non-empty-list<class-string>  $held
     * @param  non-empty-list<class-string>  $tested
     * @return non-empty-list<class-string>
     */
    public static function proven(array $held, array $tested): array
    {
        $classes = self::classes($held, $tested);

        return $classes === [] ? $tested : $classes;
    }

    /**
     * What one class a true arm resolves to becomes under its tests: itself, the tested subclasses, or nothing. A
     * tested interface keeps it, since a subclass may pass that test and no one class names the pair, but a tested
     * class replaces an interface it resolves to, since that class names a value that is both.
     *
     * @param  class-string  $class
     * @param  non-empty-list<class-string>  $tested
     * @return list<class-string>
     */
    private static function narrowedClass(string $class, array $tested): array
    {
        $subclasses = [];

        foreach ($tested as $test) {
            if (is_a($class, $test, true)) {
                return [$class];
            }

            if (is_a($test, $class, true) || (interface_exists($class) && ! interface_exists($test))) {
                $subclasses[] = $test;

                continue;
            }

            if (interface_exists($class) || interface_exists($test)) {
                return [$class];
            }
        }

        return $subclasses;
    }
}

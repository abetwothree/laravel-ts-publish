<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

/**
 * The classes Laravel itself declares. A body or a property one declares serves whatever class runs it, and a resource
 * or model one declares publishes no type, so a read treats such a class apart from an application's own.
 *
 * @internal
 */
final class FrameworkClasses
{
    /**
     * Whether Laravel declares a class: one under the `Illuminate\` namespace.
     */
    public static function is(string $fqcn): bool
    {
        return str_starts_with(ltrim($fqcn, '\\'), 'Illuminate\\');
    }
}

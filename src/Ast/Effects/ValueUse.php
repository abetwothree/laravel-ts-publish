<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

/**
 * Where a value sits that a call may run, which decides what it may run: a resource helper runs only a `Closure`,
 * while any other callee, a pipe or a computed call runs any callable spelling.
 *
 * @internal
 */
enum ValueUse
{
    /** A resource helper's callback, which Laravel's value() runs only where it is a `Closure`. */
    case Callback;

    /** An argument handed to another callee, which may run any callable; a bare local handed on is taken as data. */
    case Handed;

    /** A value called outright: a computed callee, a pipe's right side, or what a callback's fromCallable() wraps. */
    case Called;
}

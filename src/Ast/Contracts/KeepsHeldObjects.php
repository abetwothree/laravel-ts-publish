<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

/**
 * A handler whose result's `heldObject` flag the dispatcher passes on: one that resolves a closure, a first-class
 * callable or an Inertia prop object, or a bare variable holding one. Any other handler's result reads a value from
 * such an object, as `when()` calls a closure, so the dispatcher drops the flag.
 *
 * @internal
 */
interface KeepsHeldObjects {}

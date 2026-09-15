<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MorphPivot;

/**
 * Deliberately not an Eloquent Model — using() takes no native parameter type, so Laravel accepts
 * this at runtime; proves morphPivotKey()'s own is_a() guard, not just its docblock bound.
 */
final class NotAModelPivot {}

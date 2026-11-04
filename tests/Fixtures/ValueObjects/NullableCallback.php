<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/** A value whose override is an arrow function returning a union, which owns every pipe after its arrow. */
#[TsType('() => string | null')]
final class NullableCallback {}

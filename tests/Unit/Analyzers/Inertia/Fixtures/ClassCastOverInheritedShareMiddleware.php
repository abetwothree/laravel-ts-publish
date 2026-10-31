<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

/** Inherits share() whole and retypes one of the keys its parent's share() casts with a class-level cast of its own. */
#[TsCasts(['locale' => "'en'"])]
class ClassCastOverInheritedShareMiddleware extends MiddlewareWithOptionalShareCast {}

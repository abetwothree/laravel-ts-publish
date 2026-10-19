<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

/** Inherits share() whole, so the shared-data analyzer reads its parent's casts and the engine must leave them. */
class ShareCastInheritingMiddleware extends MiddlewareWithOptionalShareCast {}

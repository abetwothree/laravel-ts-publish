<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Models\Release;

/** Inherits Release::columnSummary(), so the body fallback reaches that body through the parent analyzer. */
final class InheritedFilterRelease extends Release
{
    protected $table = 'releases';
}

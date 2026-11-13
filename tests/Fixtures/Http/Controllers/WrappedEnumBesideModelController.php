<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Grade as GradeEnum;
use Workbench\App\Models\Grade;

/** A test-only controller wrapping an enum named like the model its page reads, beside a page importing that name. */
class WrappedEnumBesideModelController
{
    /** Renders nothing under a cast that imports its own `Grade`. */
    #[TsCasts(['ui' => ['type' => 'Grade', 'import' => '@js/types/ui']])]
    public function module(): Response
    {
        return Inertia::render('WrappedEnum/Module', ['ui' => null]);
    }

    /** Renders the route-bound grade model beside a wrapped case of the enum of its name. */
    public function page(Grade $grade): Response
    {
        return Inertia::render('WrappedEnum/Page', ['grade' => $grade, 'badge' => EnumResource::make(GradeEnum::Pass)]);
    }
}

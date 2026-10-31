<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Grade as GradeEnum;
use Workbench\App\Models\Grade;

/** Shares the grade model beside a wrapped case of the enum of its name, under a cast importing that name. */
final class MiddlewareWithWrappedEnumBesideModel extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['ui' => ['type' => 'Grade', 'import' => '@js/types/ui']])]
    public function share(Request $request): array
    {
        return ['ui' => $this->grade(), 'grade' => $this->grade(), 'badge' => EnumResource::make(GradeEnum::Pass)];
    }

    /** The grade every page shows. */
    private function grade(): Grade
    {
        return new Grade(['subject' => 'Math']);
    }
}

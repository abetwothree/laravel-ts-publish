<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Grade as FixtureGradeEnum;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Grade as WorkbenchGradeEnum;

/**
 * A test-only model on the `grades` table, named like both `Grade` enums it casts.
 */
class Grade extends Model
{
    protected $table = 'grades';

    /**
     * Casts one column to each `Grade` enum.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => WorkbenchGradeEnum::class,
            'subject' => FixtureGradeEnum::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Clearance;

/**
 * A test-only model on the `badges` table whose cast imports a type named like the const of the enum it casts to.
 */
#[TsCasts(['label' => ['type' => 'Clearance', 'import' => '@js/types/clearance']])]
class CustomImportBadge extends Model
{
    protected $table = 'badges';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['clearance' => Clearance::class];
    }
}

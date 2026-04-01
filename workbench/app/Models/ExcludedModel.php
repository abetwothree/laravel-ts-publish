<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Illuminate\Database\Eloquent\Model;

/**
 * Entire model excluded from TypeScript publishing via #[TsExclude].
 */
#[TsExclude]
class ExcludedModel extends Model
{
    protected $table = 'users';
}

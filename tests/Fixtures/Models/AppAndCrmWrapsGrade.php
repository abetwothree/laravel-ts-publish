<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only model on the `grades` table appending a shape that wraps the app's Status twice before the CRM's, whose
 * consts print one name (GH-160).
 */
class AppAndCrmWrapsGrade extends Model
{
    protected $table = 'grades';

    protected $appends = ['statuses'];

    /** Two app wraps, then a CRM one. */
    protected function statuses(): Attribute
    {
        return Attribute::get(fn () => [
            'a' => EnumResource::make(Status::Draft),
            'b' => EnumResource::make(Status::Published),
            'c' => EnumResource::make(CrmStatus::Lead),
        ]);
    }
}

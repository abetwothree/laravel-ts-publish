<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** A test-only model on the `posts` table whose untyped getters return bare enum cases. */
class EnumCaseGetterModel extends Model
{
    protected $table = 'posts';

    /** An untyped getter returning one case. */
    protected function defaultState(): Attribute
    {
        return Attribute::get(fn () => Status::Draft);
    }

    /** An untyped getter returning a case of either of two enums that share a name. */
    protected function eitherState(): Attribute
    {
        return Attribute::get(fn () => $this->id > 0 ? Status::Draft : CrmStatus::Lead);
    }

    /** An untyped getter returning a shape that holds a case. */
    protected function stateShape(): Attribute
    {
        return Attribute::get(fn () => ['s' => Status::Draft, 'n' => 1]);
    }
}

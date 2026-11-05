<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/** A test-only model on the `posts` table whose untyped getters return the case a vague helper returns. */
class HelperCaseGetterModel extends Model
{
    protected $table = 'posts';

    /** A case behind a mixed declaration. */
    public function draftHelper(): mixed
    {
        return Status::Draft;
    }

    /** An old-style untyped getter returning the helper's case. */
    public function getHeldOldAttribute()
    {
        return $this->draftHelper();
    }

    /** An untyped getter returning the helper's case. */
    protected function heldStatus(): Attribute
    {
        return Attribute::get(fn () => $this->draftHelper());
    }
}

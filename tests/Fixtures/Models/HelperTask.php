<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/** A test-only task on the `posts` table: a nullable assignee over `category_id` and a required team over `user_id`. */
class HelperTask extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    /** The user the task is assigned to, if any. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'category_id');
    }

    /** The team that owns the task. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'user_id');
    }
}

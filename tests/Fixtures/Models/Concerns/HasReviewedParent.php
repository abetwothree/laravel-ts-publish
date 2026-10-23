<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Category;
use Workbench\App\Models\User;

/** Declares reviewer() ahead of a parent() that redeclares the one its consumer inherits. */
trait HasReviewedParent
{
    /**
     * The user who reviewed the category.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * The category this one sits under.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
}

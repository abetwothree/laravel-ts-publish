<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A test-only model on the `posts` table related to two models that share the basename `User`. */
class DualAuthorPost extends Model
{
    protected $table = 'posts';

    /** The workbench user who wrote the post. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The CRM user sharing the author's id. */
    public function crmAuthor(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'user_id');
    }
}

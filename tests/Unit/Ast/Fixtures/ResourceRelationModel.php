<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

/**
 * A model that really declares a `resource` relation — the one exception where `$this->resource` is a
 * relation step to walk rather than the JsonResource wrapper property to skip.
 */
final class ResourceRelationModel extends Model
{
    protected $table = 'posts';

    public function resource(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

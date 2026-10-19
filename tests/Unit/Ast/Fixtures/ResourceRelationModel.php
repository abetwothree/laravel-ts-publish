<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

/**
 * A model that really declares a `resource` relation, which a resource over it reaches only as
 * `$this->resource->resource`: the JsonResource wrapper property always comes first.
 */
final class ResourceRelationModel extends Model
{
    protected $table = 'posts';

    /** The relation named `resource` the class docblock describes. */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\User;

/** A post with a relation named `resource`, which a resource over it reaches only as `$this->resource->resource`. */
final class ProxyRelationPost extends Model
{
    protected $table = 'posts';

    /** @return BelongsTo<User, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The title in capitals, a method only the post declares. */
    public function headline(): string
    {
        return strtoupper((string) $this->title);
    }
}

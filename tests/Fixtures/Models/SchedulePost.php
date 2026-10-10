<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\WeekRotaCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\WeekScheduleCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A test-only model on the `posts` table whose metadata and options cast to value objects keyed by numbers. */
class SchedulePost extends Model
{
    protected $table = 'posts';

    /**
     * The metadata and options columns' casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['metadata' => WeekScheduleCast::class, 'options' => WeekRotaCast::class];
    }

    /**
     * Another post, so a resource can spread a related model's toArray().
     *
     * @return BelongsTo<self, $this>
     */
    public function sibling(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_id');
    }
}

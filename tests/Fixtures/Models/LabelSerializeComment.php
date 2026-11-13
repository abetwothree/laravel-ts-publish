<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A comment whose post casts its columns to casters with a serialize() method. */
class LabelSerializeComment extends Model
{
    protected $table = 'comments';

    /**
     * The post the comment belongs to.
     *
     * @return BelongsTo<LabelSerializePost, $this>
     */
    public function labelPost(): BelongsTo
    {
        return $this->belongsTo(LabelSerializePost::class, 'post_id');
    }
}

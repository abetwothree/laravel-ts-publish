<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Models\User;

/**
 * A post with two members that each hold one model, though neither is a column or a plain relation: `ghost`, which only
 * an `@property-read` tag types, and a `subject` morphTo whose generic names one model.
 *
 * @property-read User|null $ghost
 */
final class SingleModelStepPost extends Model
{
    protected $table = 'posts';

    /** @return MorphTo<User, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
    }
}

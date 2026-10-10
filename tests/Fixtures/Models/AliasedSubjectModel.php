<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A test-only model whose morphTo docblock names its target by an alias that a test registers with class_alias(). */
class AliasedSubjectModel extends Model
{
    protected $table = 'facilities';

    /**
     * The model the row belongs to, named by its alias.
     *
     * @return MorphTo<FacilityAlias, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

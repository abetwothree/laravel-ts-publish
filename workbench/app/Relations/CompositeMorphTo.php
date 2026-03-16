<?php

namespace Workbench\App\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A MorphTo variant that supports composite (multi-column) foreign keys.
 *
 * Used in workbench tests to exercise the array-FK branch
 * of ModelTransformer::isMorphNullable().
 *
 * @extends MorphTo<Model, Model>
 */
class CompositeMorphTo extends MorphTo
{
    /** @var list<string> */
    protected array $compositeKeys;

    /**
     * @param  list<string>  $foreignKeys
     */
    public function __construct(Builder $query, Model $parent, array $foreignKeys, ?string $ownerKey, string $type, string $relation)
    {
        $this->compositeKeys = $foreignKeys;

        parent::__construct($query, $parent, $foreignKeys[0], $ownerKey, $type, $relation);
    }

    /**
     * @return list<string>
     */
    public function getForeignKeyName(): array
    {
        return $this->compositeKeys;
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\ValueObjects\DocblockReads\Concerns\ListsKidNames;

/**
 * A final model over the existing categories table, kept out of app/Models so it publishes no model of its own: a
 * generic `@var` names an element only when it is a model, so a value object cannot hold these probes (GH-134).
 */
final class CategoryBranch extends Model
{
    use ListsKidNames;

    protected $table = 'categories';

    protected $guarded = [];

    /** @return HasMany<CategoryBranch, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

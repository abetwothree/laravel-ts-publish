<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Artist extends Model
{
    protected $fillable = [
        'name',
    ];

    /** Reviews scoped to artists, via the subclass-only reviewable morph target */
    public function reviews(): MorphMany
    {
        return $this->morphMany(ArtistReview::class, 'reviewable');
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Venue extends Model
{
    protected $fillable = [
        'name',
    ];

    /** Reviews scoped to venues, via the subclass-only reviewable morph target */
    public function reviews(): MorphMany
    {
        return $this->morphMany(VenueReview::class, 'reviewable');
    }

    /** Labels attached via the custom Labelable pivot, which itself carries the morphTo back */
    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable')->using(Labelable::class);
    }
}

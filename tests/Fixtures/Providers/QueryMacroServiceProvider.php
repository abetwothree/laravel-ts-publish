<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

/** Registers Eloquent builder macros, two that hand the query back and one that does not, as an app's boot() would. */
class QueryMacroServiceProvider extends ServiceProvider
{
    /** Register every macro on the Eloquent builder. */
    public function boot(): void
    {
        Builder::macro('newestFirst', function () {
            return $this->latest();
        });
        Builder::macro('titleLike', function ($term) {
            return $term ? $this->where('title', 'like', '%'.$term.'%') : $this;
        });
        Builder::macro('tally', function () {
            return $this->count();
        });
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Concerns;

use Illuminate\Http\Request;

/** Supplies shared props under a name a middleware aliases to share(). */
trait SharesBase
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function shareBase(Request $request): array
    {
        return ['traitKey' => 1, 'traitName' => 'x'];
    }
}

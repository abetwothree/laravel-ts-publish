<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** An Inertia action rendering lists a sort leaves keyed: Inertia sends a list keyed out of order as an object. */
class KeyKeepingSortController
{
    /** Renders a list asort() keys out of order, one sort() re-indexes, and a list member asort() keys out of order. */
    public function show(): Response
    {
        $tags = ['b', 'a'];
        asort($tags);

        $sorted = ['b', 'a'];
        sort($sorted);

        $member = ['x' => ['b', 'a']];
        asort($member['x']);

        return Inertia::render('Sorted/Show', [
            'tags' => $tags,
            'sorted' => $sorted,
            'member' => $member,
        ]);
    }
}

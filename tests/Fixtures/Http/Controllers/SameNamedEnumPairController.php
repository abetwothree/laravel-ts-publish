<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\Deal;

/** A test-only controller whose pages read two classes of one name: two enums, and a cast's own type beside a model. */
class SameNamedEnumPairController
{
    public function pair(Deal $deal): Response
    {
        return Inertia::render('SameNamed/Pair', [
            'a' => Status::Draft,
            'b' => CrmStatus::Lead,
            'c' => $deal->status,
            'd' => $deal->crm_status,
        ]);
    }

    /** Renders two same-named enums on either side of a key Laravel renumbers and a key a cast makes optional. */
    #[TsCasts(['note' => ['type' => 'string', 'optional' => true]])]
    public function aroundPrintedKeys(): Response
    {
        return Inertia::render('SameNamed/AroundPrintedKeys', [
            'a' => Status::Draft,
            7 => 'seven',
            'note' => 'x',
            'b' => CrmStatus::Lead,
        ]);
    }

    #[TsCasts(['owner' => ['type' => 'User', 'import' => '@js/types/ui']])]
    public function castBesideModel(Post $post): Response
    {
        return Inertia::render('SameNamed/CastBesideModel', [
            'owner' => $post->author,
            'author' => $post->author,
        ]);
    }
}

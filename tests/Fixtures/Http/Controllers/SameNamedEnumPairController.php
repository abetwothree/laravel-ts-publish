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

    #[TsCasts(['owner' => ['type' => 'User', 'import' => '@js/types/ui']])]
    public function castBesideModel(Post $post): Response
    {
        return Inertia::render('SameNamed/CastBesideModel', [
            'owner' => $post->author,
            'author' => $post->author,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** A test-only collection of user cards whose `$with` default adds a `version` key beside them in its response. */
class VersionedUserCardCollection extends ResourceCollection
{
    public $collects = UserCardResource::class;

    /** @var array<string, string> */
    public $with = ['version' => '1.0'];
}

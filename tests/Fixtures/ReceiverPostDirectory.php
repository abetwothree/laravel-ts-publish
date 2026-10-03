<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Workbench\App\Models\Post;

/** A test-only class whose members hold a model no other class in these tests shares a name with. */
class ReceiverPostDirectory
{
    /** @var Post[] */
    public array $owners = [];

    /**
     * The owners, as a list of posts.
     *
     * @return list<Post>
     */
    public function ownerList(): array
    {
        return [];
    }
}

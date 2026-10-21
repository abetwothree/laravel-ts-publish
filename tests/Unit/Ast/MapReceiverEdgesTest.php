<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MapReceiverEdgesResource;
use Workbench\App\Models\Post;

test('a map over no collection, or over values a write replaced, publishes no list of the closure shape', function () {
    $types = publishedResourceKeyTypes(MapReceiverEdgesResource::class, Post::class);

    expect($types['box'])->toBe('{ items: unknown[] }')
        ->and($types['literal'])->toBe('unknown')
        ->and($types['paged'])->toBe('{ id: number }[]');
});

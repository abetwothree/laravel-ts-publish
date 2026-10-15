<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\HandoverCollection;
use Workbench\App\Http\Resources\HandoverDigestCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;

// Testbench's tearDown flushes JsonResource's shared slot; a class that redeclares $wrap owns a slot nothing resets.
afterEach(function () {
    JsonResource::flushState();
    HandoverCollection::wrap('handovers');
});

it('reads the live key of the slot each class shares or redeclares', function () {
    JsonResource::wrap('items');
    HandoverCollection::wrap('crew');

    expect(ResourceWrapping::key(PostResource::class))->toBe('items')
        ->and(ResourceWrapping::key(AnonymousResourceCollection::class))->toBe('items')
        ->and(ResourceWrapping::key(HandoverDigestCollection::class))->toBe('crew');
});

it('reads a null or empty wrap as no key', function () {
    JsonResource::withoutWrapping();

    expect(ResourceWrapping::key(PostResource::class))->toBeNull()
        ->and(ResourceWrapping::key(PostFlatCollection::class))->toBeNull()
        ->and(ResourceWrapping::key(EnumResource::class))->toBeNull();
});

it('lists no override while every slot holds its declared default', function () {
    expect(ResourceWrapping::runtimeOverrides())->toBe([]);
});

it('lists each slot a runtime wrap changed once, by the class that declares it, sorted by class', function () {
    HandoverCollection::wrap('crew');
    JsonResource::withoutWrapping();

    // The subclass sharing the redeclared slot is loaded too, and reads the same change.
    expect(class_exists(HandoverDigestCollection::class))->toBeTrue()
        ->and(ResourceWrapping::runtimeOverrides())->toBe([
            JsonResource::class => null,
            HandoverCollection::class => 'crew',
        ]);
});

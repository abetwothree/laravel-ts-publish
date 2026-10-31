<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\CardPropsBuilder;

// A body read for anything but Inertia keeps its key, so a resource publishing itself never splits its memo entries.
it('spells each reading\'s memo key suffix', function () {
    expect(array_map(fn (Reading $reading): string => $reading->memoKey(), Reading::cases()))
        ->toBe(['', '@inertia', '@inertia@filtered']);
});

// resolve() runs filter() over an API resource's toArray() read whole, and over nothing a call to it returns.
it('filters an Inertia read from the API resource toArray() it enters whole', function (Reading $from, string $runs, string $method, bool $readsCall, Reading $entered) {
    expect($from->entering(new ReflectionClass($runs), $method, $readsCall))->toBe($entered);
})->with([
    'Inertia entering a resource toArray() whole' => [Reading::Inertia, UserCardResource::class, 'toArray', false, Reading::InertiaFiltered],
    'Inertia calling a resource toArray() for its value' => [Reading::Inertia, UserCardResource::class, 'toArray', true, Reading::Inertia],
    'Inertia entering another resource method' => [Reading::Inertia, UserCardResource::class, 'with', false, Reading::Inertia],
    'Inertia entering a service' => [Reading::Inertia, CardPropsBuilder::class, 'toArray', false, Reading::Inertia],
    'the filtered reading entering a service' => [Reading::InertiaFiltered, CardPropsBuilder::class, 'build', true, Reading::InertiaFiltered],
    'the encoded reading entering a resource toArray() whole' => [Reading::Encoded, UserCardResource::class, 'toArray', false, Reading::Encoded],
]);

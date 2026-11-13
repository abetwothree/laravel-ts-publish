<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\CardPropsBuilder;

/** A scope over the subject, inside a map() kept as a Collection when $inCollection is set. */
function readingScope(string $subject, bool $inCollection = false): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass($subject));
    $scope->filtersNestedResources = ! $inCollection;

    return $scope;
}

// A body read for anything but Inertia keeps its key, so a resource publishing itself never splits its memo entries.
it('spells each reading\'s memo key suffix', function () {
    expect(array_map(fn (Reading $reading): string => $reading->memoKey(), Reading::cases()))
        ->toBe(['', '@unfiltered', '@filtered', '@inertia', '@inertia@filtered']);
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
    'the unfiltered encoded reading entering a resource toArray() whole' => [Reading::EncodedUnfiltered, UserCardResource::class, 'toArray', false, Reading::Encoded],
    'the unfiltered encoded reading calling a resource toArray() for its value' => [Reading::EncodedUnfiltered, UserCardResource::class, 'toArray', true, Reading::EncodedUnfiltered],
    'the unfiltered encoded reading entering a resource with()' => [Reading::EncodedUnfiltered, UserCardResource::class, 'with', false, Reading::EncodedUnfiltered],
    'the filtered encoded reading entering a service' => [Reading::EncodedFiltered, CardPropsBuilder::class, 'build', true, Reading::EncodedFiltered],
    'the filtered encoded reading entering a resource toArray() whole' => [Reading::EncodedFiltered, UserCardResource::class, 'toArray', false, Reading::EncodedFiltered],
]);

// filter() sends a resource around null as null wherever it reaches, and it enters arrays only, never a Collection.
it('says where a JsonResource filter() reaches a value', function (Reading $reading, string $subject, bool $inCollection, bool $reaches) {
    expect($reading->filterReaches(readingScope($subject, $inCollection)))->toBe($reaches);
})->with([
    'an encoded resource body' => [Reading::Encoded, UserCardResource::class, false, true],
    'an encoded resource body inside a kept Collection' => [Reading::Encoded, UserCardResource::class, true, false],
    'an encoded service body' => [Reading::Encoded, CardPropsBuilder::class, false, false],
    'a resource body encoded with no filter over it' => [Reading::EncodedUnfiltered, UserCardResource::class, false, false],
    'a resource body Inertia reads unfiltered' => [Reading::Inertia, UserCardResource::class, false, false],
    'a service body below a filtered one' => [Reading::InertiaFiltered, CardPropsBuilder::class, false, true],
    'a service body below a filtered one inside a kept Collection' => [Reading::InertiaFiltered, CardPropsBuilder::class, true, false],
    'a service body below a filtered resource body' => [Reading::EncodedFiltered, CardPropsBuilder::class, false, true],
    'a service body below a filtered resource body inside a kept Collection' => [Reading::EncodedFiltered, CardPropsBuilder::class, true, false],
]);

// So a call returns what its caller's filter() reaches, wherever its body is declared: unfiltered in a kept Collection.
it('reads a call as filtered only where its caller\'s filter() reaches it', function (Reading $reading, string $subject, bool $inCollection, Reading $called) {
    expect($reading->calledFrom(readingScope($subject, $inCollection)))->toBe($called);
})->with([
    'a filtered value' => [Reading::InertiaFiltered, CardPropsBuilder::class, false, Reading::InertiaFiltered],
    'a filtered value inside a kept Collection' => [Reading::InertiaFiltered, CardPropsBuilder::class, true, Reading::Inertia],
    'an unfiltered Inertia value inside a kept Collection' => [Reading::Inertia, CardPropsBuilder::class, true, Reading::Inertia],
    'an encoded resource value' => [Reading::Encoded, UserCardResource::class, false, Reading::EncodedFiltered],
    'an encoded resource value inside a kept Collection' => [Reading::Encoded, UserCardResource::class, true, Reading::Encoded],
    'an encoded value no filter() reaches, such as an event\'s' => [Reading::Encoded, CardPropsBuilder::class, false, Reading::Encoded],
    'a filtered encoded value' => [Reading::EncodedFiltered, CardPropsBuilder::class, false, Reading::EncodedFiltered],
    'a filtered encoded value inside a kept Collection' => [Reading::EncodedFiltered, CardPropsBuilder::class, true, Reading::Encoded],
    'an unfiltered encoded value' => [Reading::EncodedUnfiltered, UserCardResource::class, false, Reading::EncodedUnfiltered],
]);

// An API resource's own filter() is what the encoded reading reads over it, so there the filtered one keys alike.
it('reads a body over an API resource filtered as the encoded reading does', function (Reading $reading, string $class, Reading $over) {
    expect($reading->over($class))->toBe($over);
})->with([
    'the filtered encoded reading over a resource' => [Reading::EncodedFiltered, UserCardResource::class, Reading::Encoded],
    'the filtered encoded reading over a service' => [Reading::EncodedFiltered, CardPropsBuilder::class, Reading::EncodedFiltered],
    'the encoded reading over a service' => [Reading::Encoded, CardPropsBuilder::class, Reading::Encoded],
    'the filtered Inertia reading over a resource' => [Reading::InertiaFiltered, UserCardResource::class, Reading::InertiaFiltered],
]);

// The encoded readings send a held callable as `{}` and read with the subject's own profile; neither Inertia one does.
it('groups the readings json_encode() sends', function () {
    expect(array_map(fn (Reading $reading): bool => $reading->encodes(), Reading::cases()))->toBe([true, true, true, false, false]);
});

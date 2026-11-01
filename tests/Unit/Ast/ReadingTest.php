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
        ->toBe(['', '@unfiltered', '@inertia', '@inertia@filtered']);
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
]);

// So a call inside a map() kept as a Collection returns what Inertia sends unfiltered, wherever its body is declared.
it('reads a call from inside a kept Collection as Inertia sends it unfiltered', function (Reading $reading, bool $inCollection, Reading $called) {
    expect($reading->calledFrom(readingScope(CardPropsBuilder::class, $inCollection)))->toBe($called);
})->with([
    'a filtered value' => [Reading::InertiaFiltered, false, Reading::InertiaFiltered],
    'a filtered value inside a kept Collection' => [Reading::InertiaFiltered, true, Reading::Inertia],
    'an unfiltered Inertia value inside a kept Collection' => [Reading::Inertia, true, Reading::Inertia],
    'an encoded value inside a kept Collection' => [Reading::Encoded, true, Reading::Encoded],
]);

// Both encoded readings send a held callable as `{}` and read with the subject's own profile; neither Inertia one does.
it('groups the readings json_encode() sends', function () {
    expect(array_map(fn (Reading $reading): bool => $reading->encodes(), Reading::cases()))->toBe([true, true, false, false]);
});

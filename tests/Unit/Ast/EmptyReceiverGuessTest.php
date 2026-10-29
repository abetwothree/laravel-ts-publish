<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Resources\EmptyReceiverGuess;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubFlatCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\HandoverCollection;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\RegistrarGroupCollection;
use Workbench\App\Http\Resources\SupplierCollection;
use Workbench\App\Http\Resources\UserCollection;

afterEach(function () {
    JsonResource::flushState();
});

test('listType() is the plain ResourceCollection\'s list of no items', function () {
    expect(EmptyReceiverGuess::listType())->toBe('never[]');
});

// A resource key holding the empty receiver's plain ResourceCollection sends `[]`, which only a list already admits.
test('nestedValue() adds the empty receiver\'s list beside a collection\'s own type, and leaves a list as is', function (string $collection, string $type) {
    expect(resolve(EmptyReceiverGuess::class)->nestedValue($collection)['type'] ?? null)->toBe($type);
})->with([
    'keys of its own' => [UserCollection::class, 'UserCollection | never[]'],
    'only the list, under a body of its own' => [SupplierCollection::class, 'SupplierCollection | never[]'],
    'the collected list' => [PostCollection::class, 'PostResource[]'],
]);

test('responseAdmitsEmpty() holds for a response that is the plain list under the empty receiver\'s wrap key', function (string $collection, bool $admits) {
    expect(resolve(EmptyReceiverGuess::class)->responseAdmitsEmpty($collection))->toBe($admits);
})->with([
    'body-less, { data: R[] }' => [PostCollection::class, true],
    'a body of only the list under data' => [SupplierCollection::class, true],
    'a body of only an unknown list under data' => [RegistrarGroupCollection::class, true],
    'a body with keys of its own' => [UserCollection::class, false],
    'body-less under a wrap key of its own' => [HandoverCollection::class, false],
    'body-less under its own $wrap = null' => [StubFlatCollection::class, false],
]);

test('responseAdmitsEmpty() reads the list under the shared wrap key a provider set', function () {
    JsonResource::wrap('items');
    $guess = resolve(EmptyReceiverGuess::class);

    expect($guess->responseAdmitsEmpty(PostCollection::class))->toBeTrue()
        ->and($guess->responseAdmitsEmpty(SupplierCollection::class))->toBeFalse();
});

test('responseAdmitsEmpty() holds for a bare list when neither response has a wrap key', function () {
    JsonResource::withoutWrapping();
    $guess = resolve(EmptyReceiverGuess::class);

    expect($guess->responseAdmitsEmpty(PostCollection::class))->toBeTrue()
        ->and($guess->responseAdmitsEmpty(StubFlatCollection::class))->toBeTrue()
        ->and($guess->responseAdmitsEmpty(UserCollection::class))->toBeFalse();
});

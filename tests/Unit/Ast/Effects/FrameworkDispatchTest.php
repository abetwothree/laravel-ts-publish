<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust\FrameworkDispatch;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust\FrameworkFamily;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust\ReadOnlyCalls;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\AttributeWritingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\HookedKeyPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\KeyNameWritingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\KeyWritingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\PropertyKeyPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\StrictAttributesPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\OwnWhenResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Stubs\AncestorCallingPlainPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Stubs\AncestorCallingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostCardResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\CustomKeyPost;
use Workbench\App\Models\Post;
use Workbench\App\Models\SlugPost;
use Workbench\App\Models\TaskAssignment;

it('trusts an Eloquent reader only where each model method it dispatches to only returns a literal', function (
    string $model,
    string $reader,
    bool $trusted,
) {
    $class = new ReflectionClass($model);

    expect(FrameworkDispatch::trusted(FrameworkFamily::Eloquent, $class->getMethod($reader), $class))->toBe($trusted);
})->with([
    'getKey() on a model overriding nothing' => [Post::class, 'getKey', true],
    'getKey(), past a getKeyName() returning a literal' => [CustomKeyPost::class, 'getKey', true],
    'getRouteKey(), past a getRouteKeyName() returning a literal' => [SlugPost::class, 'getRouteKey', true],
    'getKey(), past a getKeyName() that clears the pin' => [KeyNameWritingPost::class, 'getKey', false],
    'getKey(), past a getKeyName() returning a property' => [PropertyKeyPost::class, 'getKey', true],
    'getKey(), past a parent\'s getKeyName() returning a property the model hooks' => [
        HookedKeyPost::class,
        'getKey',
        false,
    ],
    'getKey(), past a trait\'s getAttribute()' => [TaskAssignment::class, 'getKey', false],
    'trashed(), whose magic read runs a getAttribute() that clears the pin' => [AttributeWritingPost::class, 'trashed', false],
    'getAttribute(), past a preventsAccessingMissingAttributes() it runs through static::' => [
        StrictAttributesPost::class,
        'getAttribute',
        false,
    ],
    'a reader the model overrides itself' => [KeyWritingPost::class, 'getKey', false],
    'a literal getter the model declares, no reader of Eloquent\'s' => [CustomKeyPost::class, 'getKeyName', false],
]);

it('walks an ancestor\'s method a framework method runs on $this, as parent:: is walked', function (
    string $model,
    bool $trusted,
) {
    require_once __DIR__.'/../../../Fixtures/Stubs/EloquentAncestorCall.php.stub';
    require_once __DIR__.'/../../../Fixtures/Stubs/AncestorCallingPost.php.stub';
    $class = new ReflectionClass($model);

    expect(FrameworkDispatch::trusted(FrameworkFamily::Eloquent, $class->getMethod('keyThroughModel'), $class))
        ->toBe($trusted);
})->with([
    'Model::getKey(), past Eloquent\'s getKeyName()' => [AncestorCallingPlainPost::class, true],
    'Model::getKey(), past a getKeyName() that clears the pin' => [AncestorCallingPost::class, false],
]);

it('trusts a resource helper only where each resource method it dispatches to is the framework\'s', function (
    string $helper,
    bool $trusted,
) {
    $method = new ReflectionMethod(JsonResource::class, $helper);

    expect(FrameworkDispatch::trusted(FrameworkFamily::ResourceHelpers, $method, new ReflectionClass(OwnWhenResource::class)))
        ->toBe($trusted);
})->with([
    'unless(), past the when() the runtime class overrides' => ['unless', false],
    'whenNotNull(), past the when() the runtime class overrides' => ['whenNotNull', false],
    'whenLoaded(), which calls no other method' => ['whenLoaded', true],
    'whenCounted(), which calls no other method' => ['whenCounted', true],
    'when() on the parent, past the override' => ['when', true],
]);

it('hands each method of the runtime class a framework method dispatches to to the judge', function (bool $writes) {
    $asked = [];
    $judge = function (ReflectionClass $class, string $method) use (&$asked, $writes): bool {
        $asked[] = $class->getName().'::'.$method;

        return $writes;
    };
    $unless = new ReflectionMethod(JsonResource::class, 'unless');

    expect(FrameworkDispatch::trusted(FrameworkFamily::ResourceHelpers, $unless, new ReflectionClass(OwnWhenResource::class), $judge))
        ->toBe(! $writes)
        ->and($asked)->toBe([OwnWhenResource::class.'::when']);
})->with([
    'an override the judge reads as writing nothing' => [false],
    'an override the judge reads as writing' => [true],
]);

it('judges a model\'s method with the judge it is handed, in place of the literal check', function () {
    $class = new ReflectionClass(KeyNameWritingPost::class);

    expect(FrameworkDispatch::trusted(
        FrameworkFamily::Eloquent,
        $class->getMethod('getKey'),
        $class,
        static fn (ReflectionClass $class, string $method): bool => false,
    ))->toBeTrue();
});

it('trusts every helper the framework lists on a resource overriding none, as this Laravel version declares it', function () {
    $resource = new ReflectionClass(PostCardResource::class);
    $untrusted = array_filter(
        ReadOnlyCalls::RESOURCE_HELPERS,
        static fn (string $helper): bool => ! FrameworkDispatch::trusted(
            FrameworkFamily::ResourceHelpers,
            $resource->getMethod($helper),
            $resource,
        ),
    );

    expect(array_values($untrusted))->toBe([]);
});

it('trusts every reader Eloquent lists on a model overriding none, as this Laravel version declares it', function () {
    $model = new ReflectionClass(Post::class);
    $untrusted = array_filter(
        ReadOnlyCalls::MODEL_READERS,
        static fn (string $reader): bool => ! $model->hasMethod($reader)
            || ! FrameworkDispatch::trusted(FrameworkFamily::Eloquent, $model->getMethod($reader), $model),
    );

    expect(array_values($untrusted))->toBe([]);
});

it('judges a model\'s method on the class PHP looks it up on, reading its properties on the class of $this', function (
    string $runtime,
    bool $mayWrite,
) {
    $judged = FrameworkFamily::Eloquent->userMethodMayWrite(
        new ReflectionClass(PropertyKeyPost::class),
        'getKeyName',
        new ReflectionClass($runtime),
    );

    expect($judged)->toBe($mayWrite);
})->with([
    'run on the class that declares it' => [PropertyKeyPost::class, false],
    'run on a subclass that hooks the property it returns' => [HookedKeyPost::class, true],
]);

it('tells the methods each family declares', function (FrameworkFamily $family, string $class, string $method, bool $declares) {
    expect($family->declares(new ReflectionMethod($class, $method)))->toBe($declares);
})->with([
    'a helper on JsonResource\'s trait' => [FrameworkFamily::ResourceHelpers, JsonResource::class, 'when', true],
    'a helper a resource overrides' => [FrameworkFamily::ResourceHelpers, OwnWhenResource::class, 'when', false],
    'a reader on Eloquent\'s trait' => [FrameworkFamily::Eloquent, Post::class, 'getAttribute', true],
    'a reader a model overrides' => [FrameworkFamily::Eloquent, CustomKeyPost::class, 'getKeyName', false],
    'a reader on the SoftDeletes trait' => [FrameworkFamily::Eloquent, Post::class, 'trashed', true],
    'an Eloquent method, to the resource family' => [FrameworkFamily::ResourceHelpers, Post::class, 'getKey', false],
    'a resource helper, to the Eloquent family' => [FrameworkFamily::Eloquent, JsonResource::class, 'when', false],
]);

it('trusts a computed call only in Eloquent\'s code', function (FrameworkFamily $family, bool $trusts) {
    expect($family->trustsComputedCalls())->toBe($trusts);
})->with([
    'Eloquent' => [FrameworkFamily::Eloquent, true],
    'the resource helpers' => [FrameworkFamily::ResourceHelpers, false],
]);

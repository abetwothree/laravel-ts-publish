<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\FormRequestTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Workbench\App\Enums\Status;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\User;

it('round-trips a model transformer through serialize without transients', function () {
    $original = new ModelTransformer(User::class);

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(ModelTransformer::class)
        ->and($restored->modelName)->toBe($original->modelName)
        ->and($restored->namespacePath)->toBe($original->namespacePath)
        ->and($restored->columns)->toBe($original->columns)
        ->and($restored->globalAliasMap())->toBe($original->globalAliasMap());
});

it('does not retain reflection state after restore', function () {
    $restored = unserialize(serialize(new ModelTransformer(User::class)));

    $r = new ReflectionObject($restored);

    expect($r->getProperty('reflectionModel')->isInitialized($restored))->toBeFalse();
});

it('round-trips a resource transformer through serialize without transients', function () {
    $original = new ResourceTransformer(PostResource::class);

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(ResourceTransformer::class)
        ->and($restored->resourceName)->toBe($original->resourceName);
});

it('round-trips an enum transformer through serialize without transients', function () {
    $original = new EnumTransformer(Status::class);

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(EnumTransformer::class)
        ->and($restored->cases)->toBe($original->cases)
        ->and($restored->enumName)->toBe($original->enumName);
});

it('round-trips a form request transformer through serialize without transients', function () {
    $original = new FormRequestTransformer(StorePostRequest::class);

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(FormRequestTransformer::class)
        ->and($restored->fields)->toBe($original->fields)
        ->and($restored->typeName)->toBe($original->typeName);
});

it('round-trips a broadcast event transformer through serialize without transients', function () {
    $original = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(BroadcastEventTransformer::class)
        ->and($restored->properties)->toBe($original->properties)
        ->and($restored->eventName)->toBe($original->eventName);
});

it('round-trips a route transformer through serialize without transients', function () {
    $original = new RouteTransformer(PostController::class);

    $restored = unserialize(serialize($original));

    expect($restored)->toBeInstanceOf(RouteTransformer::class)
        ->and($restored->controllerName)->toBe($original->controllerName)
        ->and($restored->actions)->toBe($original->actions);
});

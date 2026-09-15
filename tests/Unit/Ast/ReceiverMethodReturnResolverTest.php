<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;
use Workbench\App\Models\UuidPost;

test('getKey on an abstract or base Model bound declines', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);

    expect(resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of(Model::class), 'getKey', $scope))->toBeNull();
});

test('getKey and modelKeys follow the receiver model key type, not the scope model', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
    $resolver = resolve(ReceiverMethodReturnResolver::class);

    expect($resolver->resolve(ReceiverType::of(Post::class), 'getKey', $scope))->toBe(['type' => 'number', 'optional' => false])
        ->and($resolver->resolve(ReceiverType::of(UuidPost::class), 'getKey', $scope))->toBe(['type' => 'string', 'optional' => false])
        ->and($resolver->resolve(ReceiverType::of(Product::class), 'getKey', $scope))->toBe(['type' => 'string', 'optional' => false])
        ->and($resolver->resolve(new ReceiverType([EloquentCollection::class], elementModel: UuidPost::class), 'modelKeys', $scope))
        ->toBe(['type' => 'string[]', 'optional' => false])
        ->and($resolver->resolve(new ReceiverType([User::class, Post::class]), 'getKey', $scope))
        ->toBe(['type' => 'number', 'optional' => false])
        ->and($resolver->resolve(new ReceiverType([UuidPost::class, Post::class]), 'getKey', $scope))
        ->toBe(['type' => 'string | number', 'optional' => false]);
});

test('modelKeys needs an Eloquent collection of a known model', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);

    expect(resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of(Collection::class), 'modelKeys', $scope))->toBeNull();
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Workbench\App\Models\CompositeComment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\User;

test('resolveAttribute returns empty info for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAttribute('App\\Models\\NonExistent', 'name');

    expect($result)->toBe(LaravelTsPublish::emptyTypeScriptInfo());
});

test('resolveAttribute returns empty info when DB type maps to unknown', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // 'search_index' on Order has type 'unknown' in the DB schema
    $result = $resolver->resolveAttribute(Order::class, 'search_index');

    expect($result['type'])->toBe('unknown');
});

test('resolveRelation returns unknown for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveRelation('App\\Models\\NonExistent', 'posts');

    expect($result)->toBe(['type' => 'unknown', 'modelFqcn' => null]);
});

test('resolveMethodReturnType returns empty info for non-existent method', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveMethodReturnType(User::class, 'nonExistentMethod');

    expect($result)->toBe(LaravelTsPublish::emptyTypeScriptInfo());
});

test('resolveMethodReturnType returns empty info for non-existent class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveMethodReturnType('App\\Models\\NonExistent', 'nonExistentMethod');

    expect($result)->toBe(LaravelTsPublish::emptyTypeScriptInfo());
});

test('resolveAccessorModelFqcn returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $result = $resolver->resolveAccessorModelFqcn('App\\Models\\NonExistent', 'name');

    expect($result)->toBeNull();
});

test('resolveAccessorModelFqcn returns null for non-accessor attribute', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // 'name' is a regular string column, not an accessor
    $result = $resolver->resolveAccessorModelFqcn(User::class, 'name');

    expect($result)->toBeNull();
});

test('resolveAccessorModelFqcn returns null when accessor does not return a Model', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // 'initials' is an accessor that returns string, not a Model
    $result = $resolver->resolveAccessorModelFqcn(User::class, 'initials');

    expect($result)->toBeNull();
});

test('getAttributes returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getAttributes('App\\Models\\NonExistent'))->toBeNull();
});

test('getRelations returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getRelations('App\\Models\\NonExistent'))->toBeNull();
});

test('getRelationNullable returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getRelationNullable('App\\Models\\NonExistent'))->toBeNull();
});

test('getInstance returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getInstance('App\\Models\\NonExistent'))->toBeNull();
});

test('getReflection returns null for non-existent model class', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    expect($resolver->getReflection('App\\Models\\NonExistent'))->toBeNull();
});

test('buildMorphTargetMap builds map from MorphMany inverse relations', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Product::class,
        Image::class,
    ]);

    // User, Post, and Product all have morphMany(Image::class, 'imageable')
    $targets = $resolver->getMorphToTargets(Image::class);

    expect($targets)->toBe([Post::class, Product::class, User::class]);
});

test('getMorphToTargets returns empty array when no inverse relations exist', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Image::class,
    ]);

    // CompositeComment has no inverse MorphOne/MorphMany relations in the scanned models
    expect($resolver->getMorphToTargets(CompositeComment::class))->toBe([]);
});

test('getMorphToTargets returns empty array when map is not built', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // No buildMorphTargetMap() call — default empty map
    expect($resolver->getMorphToTargets(Image::class))->toBe([]);
});

test('buildMorphTargetMap skips non-existent model classes', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // Should not throw, just skip the non-existent class
    $resolver->buildMorphTargetMap([
        'App\\Models\\NonExistent',
        User::class,
        Image::class,
    ]);

    $targets = $resolver->getMorphToTargets(Image::class);

    expect($targets)->toBe([User::class]);
});

test('resolveRelation returns union type for MorphTo when targets exist', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    $resolver->buildMorphTargetMap([
        User::class,
        Post::class,
        Product::class,
        Image::class,
    ]);

    $result = $resolver->resolveRelation(Image::class, 'imageable');

    expect($result['type'])->toBe('Post | Product | User')
        ->and($result['modelFqcn'])->toBeNull();
});

test('resolveRelation returns unknown for MorphTo when no targets exist', function () {
    $resolver = resolve(ModelAttributeResolver::class);

    // Build map with only Image — no inverse relations for CompositeComment
    $resolver->buildMorphTargetMap([Image::class]);

    $result = $resolver->resolveRelation(CompositeComment::class, 'commentable');

    // CompositeComment has nullable FK columns, so it gets ' | null' appended
    expect($result['type'])->toBe('unknown | null')
        ->and($result['modelFqcn'])->toBeNull();
});

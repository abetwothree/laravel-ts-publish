<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\RelationMap;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

beforeEach(function () {
    $reflection = new ReflectionClass(RelationMap::class);
    $prop = $reflection->getProperty('map');
    $prop->setValue(null, null);
});

test('gather returns default strategies for all known relation types', function () {
    $map = (new RelationMap)->gather();

    expect($map)
        ->toBeArray()
        ->toHaveKey(HasOne::class)
        ->toHaveKey(MorphOne::class)
        ->toHaveKey(HasOneThrough::class)
        ->toHaveKey(BelongsTo::class)
        ->toHaveKey(MorphTo::class)
        ->toHaveKey(HasMany::class)
        ->toHaveKey(HasManyThrough::class)
        ->toHaveKey(BelongsToMany::class)
        ->toHaveKey(MorphMany::class)
        ->toHaveKey(MorphToMany::class)
        ->and($map[HasOne::class])->toBe('nullable')
        ->and($map[MorphOne::class])->toBe('nullable')
        ->and($map[HasOneThrough::class])->toBe('nullable')
        ->and($map[BelongsTo::class])->toBe('fk')
        ->and($map[MorphTo::class])->toBe('morph')
        ->and($map[HasMany::class])->toBe('never')
        ->and($map[HasManyThrough::class])->toBe('never')
        ->and($map[BelongsToMany::class])->toBe('never')
        ->and($map[MorphMany::class])->toBe('never')
        ->and($map[MorphToMany::class])->toBe('never');
});

test('gather caches the result on subsequent calls', function () {
    $map1 = (new RelationMap)->gather();
    $map2 = (new RelationMap)->gather();

    expect($map1)->toBe($map2);
});

test('gather merges relation_nullability_map config overrides', function () {
    config()->set('ts-publish.models.relation_nullability_map', [
        BelongsTo::class => 'nullable',
    ]);

    $map = (new RelationMap)->gather();

    expect($map[BelongsTo::class])->toBe('nullable');
});

test('config overrides take precedence over defaults', function () {
    config()->set('ts-publish.models.relation_nullability_map', [
        HasOne::class => 'never',
        HasMany::class => 'nullable',
    ]);

    $map = (new RelationMap)->gather();

    expect($map[HasOne::class])->toBe('never')
        ->and($map[HasMany::class])->toBe('nullable');
});

test('config can add custom relation types not in defaults', function () {
    config()->set('ts-publish.models.relation_nullability_map', [
        'App\\Relations\\CustomRelation' => 'nullable',
    ]);

    $map = (new RelationMap)->gather();

    expect($map)->toHaveKey('App\\Relations\\CustomRelation')
        ->and($map['App\\Relations\\CustomRelation'])->toBe('nullable');
});

test('strategyFor resolves short class names to FQCN strategies', function () {
    $relationMap = new RelationMap;

    expect($relationMap->strategyFor('HasOne'))->toBe('nullable')
        ->and($relationMap->strategyFor('BelongsTo'))->toBe('fk')
        ->and($relationMap->strategyFor('MorphTo'))->toBe('morph')
        ->and($relationMap->strategyFor('HasMany'))->toBe('never');
});

test('strategyFor resolves FQCNs directly', function () {
    $relationMap = new RelationMap;

    expect($relationMap->strategyFor(HasOne::class))->toBe('nullable')
        ->and($relationMap->strategyFor(BelongsTo::class))->toBe('fk');
});

test('strategyFor resolves custom package relation by short name', function () {
    config()->set('ts-publish.models.relation_nullability_map', [
        'SomePackage\\Relations\\BelongsToTenant' => 'never',
    ]);

    $relationMap = new RelationMap;

    expect($relationMap->strategyFor('BelongsToTenant'))->toBe('never');
});

test('strategyFor defaults to nullable for unknown relation types', function () {
    $relationMap = new RelationMap;

    expect($relationMap->strategyFor('UnknownRelation'))->toBe('nullable');
});

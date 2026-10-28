<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\RelationMap;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Ancestors;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Descendants;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\FirstRelated;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Relations\Siblings;
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
use Workbench\App\Relations\CompositeMorphTo;

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

test('isToMany reads the short type, and a subclass of a to-many relation that keeps how an eager load starts or how it loads the collection', function () {
    $toMany = fn (string $class): bool => RelationMap::isToMany(class_basename($class), $class);

    expect($toMany(HasMany::class))->toBeTrue()
        ->and($toMany(HasManyThrough::class))->toBeTrue()
        ->and($toMany(BelongsToMany::class))->toBeTrue()
        ->and($toMany(MorphMany::class))->toBeTrue()
        ->and($toMany(MorphToMany::class))->toBeTrue()
        ->and($toMany(Ancestors::class))->toBeTrue()
        ->and($toMany(Descendants::class))->toBeTrue()
        ->and($toMany(Siblings::class))->toBeTrue()
        ->and($toMany(FirstRelated::class))->toBeFalse()
        ->and($toMany(HasOne::class))->toBeFalse()
        ->and($toMany(HasOneThrough::class))->toBeFalse()
        ->and($toMany(MorphOne::class))->toBeFalse()
        ->and($toMany(BelongsTo::class))->toBeFalse()
        ->and($toMany(MorphTo::class))->toBeFalse()
        ->and($toMany(CompositeMorphTo::class))->toBeFalse()
        ->and(RelationMap::isToMany('HasManyDeep'))->toBeTrue()
        ->and(RelationMap::isToMany('Ancestors'))->toBeFalse();
});

test('strategyFor gives a relation subclass the map does not name the strategy of its nearest parent the map names', function () {
    $relationMap = new RelationMap;

    expect($relationMap->strategyFor('Ancestors', Ancestors::class))->toBe('never')
        ->and($relationMap->strategyFor('CompositeMorphTo', CompositeMorphTo::class))->toBe('morph')
        ->and($relationMap->strategyFor('Ancestors'))->toBe('nullable');
});

test('strategyFor never gives a to-many parent\'s strategy to a subclass that loads one model', function () {
    expect((new RelationMap)->strategyFor('FirstRelated', FirstRelated::class))->toBe('nullable');
});

test('strategyFor keeps a mapping that names the subclass over its parent\'s', function () {
    config()->set('ts-publish.models.relation_nullability_map', [
        Ancestors::class => 'nullable',
    ]);

    expect((new RelationMap)->strategyFor('Ancestors', Ancestors::class))->toBe('nullable');
});

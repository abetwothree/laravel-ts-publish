<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedDefaultsSubject;
use Workbench\App\Http\Resources\PostStatsResource;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Models\Post;

describe('SubjectPropertyTypeResolver::resolve()', function () {
    test('an untyped property default types itself only when it is uniform', function () {
        $subject = new ReflectionClass(UntypedDefaultsSubject::class);
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->resolve($subject, 'extensions')['type'] ?? null)->toBe('string[]')
            ->and($resolver->resolve($subject, 'limit')['type'] ?? null)->toBe('number')
            ->and($resolver->resolve($subject, 'mixed'))->toBeNull();
    });

    test('a declared type still wins over the default literal', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->resolve(new ReflectionClass(PostStatsResource::class), 'untypedChannels')['type'] ?? null)
            ->toBe('string[]');
    });
});

describe('SubjectPropertyTypeResolver::declaresOwnProperty()', function () {
    test('framework-declared resource properties never count as the subject own', function () {
        expect(resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty(new ReflectionClass(PostStatsResource::class), 'resource'))->toBeFalse()
            ->and(resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty(new ReflectionClass(PostStatsResource::class), 'stats'))->toBeTrue();
    });

    // preserveKeys is the one name Laravel reads with property_exists() instead of declaring, so it is
    // the subject's own; every name the framework really declares stays excluded.
    test('an inherited framework name is excluded, while preserveKeys is the subject own', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $collection = new ReflectionClass(PreserveKeysFlatCollection::class);

        expect($resolver->declaresOwnProperty($collection, 'collects'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'collection'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'wrap'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'additional'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'preserveKeys'))->toBeTrue();
    });

    test('a model subject never counts an Eloquent property as its own', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->declaresOwnProperty(new ReflectionClass(Post::class), 'fillable'))->toBeFalse()
            ->and($resolver->declaresOwnProperty(new ReflectionClass(Post::class), 'nothingDeclaresThis'))->toBeFalse();
    });
});

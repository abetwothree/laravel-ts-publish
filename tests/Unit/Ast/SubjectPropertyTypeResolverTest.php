<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DynamicDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\InheritedDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OwnFillableModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReassignedDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UnloadableDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedDefaultsSubject;
use Workbench\App\Http\Resources\ModelWrappedPropResource;
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

    // A write in any method the class runs, an ancestor's included, means a read may not hold the default.
    test('an untyped default types its property only while no method the class runs writes it', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $parent = new ReflectionClass(ReassignedDefaultsSubject::class);
        $child = new ReflectionClass(InheritedDefaultsSubject::class);

        expect($resolver->resolve($parent, 'note'))->toBeNull()
            ->and($resolver->resolve($parent, 'tags'))->toBeNull()
            ->and($resolver->resolve($parent, 'views'))->toBeNull()
            ->and($resolver->resolve($parent, 'label')['type'] ?? null)->toBe('string')
            ->and($resolver->resolve($child, 'note'))->toBeNull()
            ->and($resolver->resolve($child, 'label'))->toBeNull()
            ->and($resolver->resolve(new ReflectionClass(DynamicDefaultsSubject::class), 'status'))->toBeNull();
    });

    test('a default naming a constant this process cannot load declines instead of throwing', function () {
        $subject = new ReflectionClass(UnloadableDefaultsSubject::class);
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->resolve($subject, 'mode'))->toBeNull()
            ->and($resolver->resolve($subject, 'flag'))->toBeNull()
            ->and(collect(resolve(AstEngine::class)->analyzePublicProperties(UnloadableDefaultsSubject::class)->properties)->pluck('type', 'name')->all())
            ->toBe(['mode' => 'unknown', 'flag' => 'unknown']);
    });

    test('a declared type still wins over the default literal', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->resolve(new ReflectionClass(PostStatsResource::class), 'untypedChannels')['type'] ?? null)
            ->toBe('string[]');
    });
});

describe('SubjectPropertyTypeResolver::declaresOwnProperty()', function () {
    test('a property the subject itself declares is its own', function () {
        expect(resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty(new ReflectionClass(PostStatsResource::class), 'stats'))
            ->toBeTrue();
    });

    // These never reach the framework-base loop: each is rejected earlier, by the `Illuminate\`
    // declaring-class guard, by isStatic() for the redeclared `wrap`, or by hasProperty().
    test('an inherited, static or absent property is rejected by the earlier guards', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $collection = new ReflectionClass(PreserveKeysFlatCollection::class);

        expect($resolver->declaresOwnProperty(new ReflectionClass(PostStatsResource::class), 'resource'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'collection'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'additional'))->toBeFalse()
            ->and($resolver->declaresOwnProperty($collection, 'wrap'))->toBeFalse()
            ->and($resolver->declaresOwnProperty(new ReflectionClass(Post::class), 'nothingDeclaresThis'))->toBeFalse();
    });

    // Every subject here REDECLARES the name, so its declaring class is its own and only the
    // FRAMEWORK_BASES loop can reject it: delete one arm and its assertion fails.
    test('a redeclared framework name is rejected by the framework-base loop', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->declaresOwnProperty(new ReflectionClass(ModelWrappedPropResource::class), 'resource'))->toBeFalse()
            ->and($resolver->declaresOwnProperty(new ReflectionClass(PreserveKeysFlatCollection::class), 'collects'))->toBeFalse()
            ->and($resolver->declaresOwnProperty(new ReflectionClass(OwnFillableModel::class), 'fillable'))->toBeFalse();
    });

    // preserveKeys is the one name Laravel reads with property_exists() instead of declaring, so no
    // framework base holds it and it stays the subject's own.
    test('preserveKeys is the subject own, because no framework base declares it', function () {
        expect(resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty(new ReflectionClass(PreserveKeysFlatCollection::class), 'preserveKeys'))
            ->toBeTrue();
    });
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ForeignWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\OverriddenWithResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ByReferenceSelfChild;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DynamicDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\InheritedDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\OwnFillableModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReassignedDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SortedDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UnloadableDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedDefaultsSubject;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\WriteKindsSubject;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\ModelWrappedPropResource;
use Workbench\App\Http\Resources\PostStatsResource;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Models\Post;

describe('SubjectPropertyTypeResolver::resolve()', function () {
    test('an untyped property default types itself as a parameter default does', function (string $property, ?string $type) {
        $subject = new ReflectionClass(UntypedDefaultsSubject::class);

        expect(resolve(SubjectPropertyTypeResolver::class)->resolve($subject, $property, RuntimeClass::assumed($subject))['type'] ?? null)
            ->toBe($type);
    })->with([
        'a list' => ['extensions', 'string[]'],
        'a scalar' => ['limit', 'number'],
        'a mixed list' => ['mixed', '(string | number)[]'],
        'no value, which PHP reads as null' => ['pending', 'null'],
        'an enum case' => ['stage', 'StatusType'],
        'a record holding a list' => ['limits', '{ daily: number; codes: string[] }'],
        'an empty array' => ['notes', 'never[]'],
        'int keys, kept here and re-indexed by the API resource publisher' => ['positions', '{ "1": string; "2": string }'],
        // Code outside the class fills a public property declared without a value.
        'a public property with no value' => ['slot', null],
    ]);

    test('an enum case default carries the enum for its import', function () {
        $subject = new ReflectionClass(UntypedDefaultsSubject::class);

        expect(resolve(SubjectPropertyTypeResolver::class)->resolve($subject, 'stage', RuntimeClass::assumed($subject)))
            ->toBe(['type' => 'StatusType', 'optional' => false, 'directEnumFqcn' => Status::class]);
    });

    // end() only moves the pointer, and a method PHP routes to __call() or __callStatic() takes its arguments by value.
    // A receiver the scan cannot resolve is read as passing by value.
    test('an argument passed to a by-reference parameter counts as a write', function (string $property, ?string $type) {
        $subject = new ReflectionClass(ByReferenceDefaultsSubject::class);

        expect(resolve(SubjectPropertyTypeResolver::class)->resolve($subject, $property, RuntimeClass::assumed($subject))['type'] ?? null)
            ->toBe($type);
    })->with([
        'array_push()' => ['queue', null],
        'a named preg_match() argument' => ['matches', null],
        'a method of $this' => ['filled', null],
        'a self:: method' => ['stashed', null],
        'a parent:: method' => ['parentFilled', null],
        'a constructor of a named class' => ['constructed', null],
        'a variadic parameter' => ['parsed', null],
        'end(), which only moves the pointer' => ['cursor', 'string[]'],
        'count(), which takes it by value' => ['counted', 'string[]'],
        'a receiver the scan cannot resolve' => ['opaque', 'string[]'],
        'a self:: method the class does not declare' => ['missing', 'string[]'],
    ]);

    // The base's body calls its own by-reference `stash()` and `fill()`, not the subclass's by-value namesakes.
    test('a by-reference write in a base body counts through the base methods, not a subclass namesake', function (string $property) {
        $child = new ReflectionClass(ByReferenceSelfChild::class);

        expect(resolve(SubjectPropertyTypeResolver::class)->resolve($child, $property, RuntimeClass::assumed($child)))->toBeNull();
    })->with(['stashed', 'filled']);

    // A method called on the property is not passed the property by reference.
    test('a by-reference function writes the property, and a call on the property does not', function () {
        $subject = new ReflectionClass(SortedDefaultsSubject::class);
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->resolve($subject, 'tags', RuntimeClass::assumed($subject)))->toBeNull()
            ->and($resolver->resolve($subject, 'items', RuntimeClass::assumed($subject))['type'] ?? null)->toBe('string[]');
    });

    // A write in any method the class runs, an ancestor's included, may change what a read holds. A computed name may
    // leave anything there.
    test('an untyped default types its property joined with the values its writes assign, an append included', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $parent = new ReflectionClass(ReassignedDefaultsSubject::class);
        $child = new ReflectionClass(InheritedDefaultsSubject::class);
        $dynamic = new ReflectionClass(DynamicDefaultsSubject::class);
        $read = fn (object $subject, string $name): mixed => (fn () => $this->{$name})->call($subject);
        $viewed = new ReassignedDefaultsSubject('kept');
        $viewed->view();
        $viewed->tag();

        expect($resolver->resolve($parent, 'note', RuntimeClass::assumed($parent))['type'] ?? null)->toBe('string | null')
            ->and($resolver->resolve($parent, 'tags', RuntimeClass::assumed($parent))['type'] ?? null)->toBe('(string | number)[]')
            ->and($resolver->resolve($parent, 'views', RuntimeClass::assumed($parent))['type'] ?? null)->toBe('number')
            ->and($resolver->resolve($parent, 'label', RuntimeClass::assumed($parent))['type'] ?? null)->toBe('string')
            ->and($resolver->resolve($child, 'note', RuntimeClass::assumed($child))['type'] ?? null)->toBe('string | null')
            ->and($resolver->resolve($child, 'label', RuntimeClass::assumed($child))['type'] ?? null)->toBe('string | null')
            ->and($resolver->resolve($dynamic, 'status', RuntimeClass::assumed($dynamic)))->toBeNull()
            ->and([$read(new ReassignedDefaultsSubject, 'note'), $read($viewed, 'note'), $read($viewed, 'views')])
            ->toBe([null, 'kept', 1])
            ->and($read($viewed, 'tags'))->toBe(['news', 'guide', 5]);
    });

    test('a default naming a constant this process cannot load declines instead of throwing', function () {
        $subject = new ReflectionClass(UnloadableDefaultsSubject::class);
        $resolver = resolve(SubjectPropertyTypeResolver::class);

        expect($resolver->resolve($subject, 'mode', RuntimeClass::assumed($subject)))->toBeNull()
            ->and($resolver->resolve($subject, 'flag', RuntimeClass::assumed($subject)))->toBeNull()
            ->and(collect(resolve(AstEngine::class)->analyzePublicProperties(UnloadableDefaultsSubject::class)->properties)->pluck('type', 'name')->all())
            ->toBe(['mode' => 'unknown', 'flag' => 'unknown']);
    });

    test('a declared type still wins over the default literal', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $stats = new ReflectionClass(PostStatsResource::class);

        expect($resolver->resolve($stats, 'untypedChannels', RuntimeClass::assumed($stats))['type'] ?? null)
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

describe('SubjectPropertyTypeResolver::holdsDefault()', function () {
    // A framework file is walked for writes only where its source spells the name, which a write it counts must.
    test('a framework method that writes the property still counts, and one that only reads it does not', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $subject = new ReflectionClass(PostStatsResource::class);

        expect($resolver->holdsDefault($subject, 'additional'))->toBeFalse()
            ->and($resolver->holdsDefault($subject, 'with'))->toBeTrue();
    });

    // The write-site filter and the check on each site share one list of node kinds, so each kind must count.
    test('each kind of node that writes a property stops a read holding its default', function (string $property, bool $holds) {
        expect(resolve(SubjectPropertyTypeResolver::class)->holdsDefault(new ReflectionClass(WriteKindsSubject::class), $property))
            ->toBe($holds);
    })->with([
        'no write' => ['untouched', true],
        'Assign' => ['assigned', false],
        'AssignOp' => ['compound', false],
        'AssignRef' => ['referenced', false],
        'PreInc' => ['preIncremented', false],
        'PreDec' => ['preDecremented', false],
        'PostInc' => ['postIncremented', false],
        'PostDec' => ['postDecremented', false],
        'Foreach_ key' => ['foreachKey', false],
        'Foreach_ value' => ['foreachValue', false],
        'Foreach_ by reference' => ['foreachReference', false],
        'Unset_' => ['unsetOne', false],
        'CallLike' => ['pushed', false],
    ]);

    // A body's write sites are collected once and reused for every later name, so the order the names are asked in
    // must not change an answer.
    test('a class body read for one name answers the next from the same write sites', function (array $names) {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $subject = new ReflectionClass(PostStatsResource::class);
        $answers = [];

        foreach ($names as $name) {
            $answers[$name] = $resolver->holdsDefault($subject, $name);
        }

        expect($answers)->toMatchArray(['additional' => false, 'with' => true]);
    })->with([
        'the written name first' => [['additional', 'with']],
        'the read-only name first' => [['with', 'additional']],
    ]);

    test('a write in a trait method the class overrides leaves the default held, since it never runs', function () {
        expect(resolve(SubjectPropertyTypeResolver::class)->holdsDefault(new ReflectionClass(OverriddenWithResource::class), 'with'))
            ->toBeTrue()
            ->and(analyzedKeyTypes(OverriddenWithResource::class))->toBe(['version' => 'string'])
            ->and(json_encode((new OverriddenWithResource(null))->resolve(request())))->toBe('{"version":"1.0"}')
            ->and(json_encode((new OverriddenWithResource(null))->retag()->resolve(request())))->toBe('{"version":"1.0"}');
    });

    test('a write on an anonymous object or in a static closure leaves the default held, since it is never the subject\'s', function () {
        expect(resolve(SubjectPropertyTypeResolver::class)->holdsDefault(new ReflectionClass(ForeignWithResource::class), 'with'))
            ->toBeTrue()
            ->and(analyzedKeyTypes(ForeignWithResource::class))->toBe(['version' => 'string'])
            ->and(json_encode((new ForeignWithResource(null))->relabel()->resolve(request())))->toBe('{"version":"1.0"}');
    });

    // The source is read once a run, so a later read must still record the file, or a cached publish misses an update.
    test('a framework file whose source a read skips is recorded as a dependency on every read', function () {
        $resolver = resolve(SubjectPropertyTypeResolver::class);
        $subject = new ReflectionClass(PostStatsResource::class);
        $file = (string) new ReflectionClass(JsonResource::class)->getFileName();

        DependencyRecorder::start();

        try {
            $resolver->holdsDefault($subject, 'untypedChannels');
            $mark = DependencyRecorder::mark();
            $resolver->holdsDefault($subject, 'untypedChannels');
            $recorded = DependencyRecorder::since($mark);
        } finally {
            DependencyRecorder::stop();
        }

        expect($recorded)->toContain($file);
    });
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\MacroCallResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MacroScopeLeakResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MacroShadowingChainResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MacroShadowingCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MacroShadowingResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins\TicketMacro;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MacroShadowedPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers\ResourceMacroServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

/**
 * A post the macro fixtures serialize, so the runtime value each macro sends can be read.
 */
function macroShadowedPost(): MacroShadowedPost
{
    $post = new MacroShadowedPost;
    $post->forceFill(['id' => 3, 'title' => 'hello big world']);

    return $post;
}

/**
 * What Laravel sends for a resource built over the macro fixtures' post, decoded from its JSON.
 *
 * @return array<array-key, mixed>
 */
function sentByMacroResource(JsonResource $resource): array
{
    /** @var array<array-key, mixed> */
    return json_decode((string) json_encode($resource->resolve(Request::create('/'))), true);
}

// The macro store is process-static, so a macro left registered would leak into every later test in the worker.
afterEach(fn () => JsonResource::flushMacros());

describe('with the resource macros a provider registers', function () {
    beforeEach(fn () => app()->register(ResourceMacroServiceProvider::class, force: true));

    it('types a call a resource macro answers from the macro, never from the model method it shadows', function (string $key, string $type) {
        expect(publishedResourceKeyTypes(MacroShadowingResource::class, MacroShadowedPost::class)[$key] ?? null)->toBe($type);
    })->with([
        'a bare call' => ['label', 'string'],
        'a nullsafe call on $this' => ['label_nullsafe', 'string'],
        'a call on $this->resource, which runs the model' => ['label_proxy', 'number'],
        'static::' => ['label_static', 'string'],
        '$this::' => ['label_this_static', 'string'],
        'self::' => ['label_self', 'string'],
        'a chain through a macro\'s native return class' => ['moment_length', 'number'],
        'a chain through a static:: macro call' => ['moment_length_static', 'number'],
        'an untyped closure, read by its body' => ['shout', 'string'],
        'a typed closure' => ['typed_shout', 'string'],
        'a mixin closure' => ['badge', 'string'],
        'a mixin closure whose body the engine cannot type' => ['word_count', 'unknown'],
        'a mixin closure read by its body' => ['title_length', 'number'],
        'an invokable object' => ['ticket', 'number'],
        'a closure typed by its @return' => ['codes', 'string[]'],
        'an arrow function typed by its @return' => ['arrow_doc', 'number'],
        'static:: on a macro no model method shares' => ['shout_static', 'string'],
    ]);

    it('sends what each resource macro returns', function () {
        expect(sentByMacroResource(new MacroShadowingResource(macroShadowedPost())))->toBe([
            'label' => 'macro-label',
            'label_nullsafe' => 'macro-label',
            'label_proxy' => 7,
            'label_static' => 'macro-label',
            'label_this_static' => 'macro-label',
            'label_self' => 'macro-label',
            'moment_length' => 4,
            'moment_length_static' => 4,
            'shout' => 'HELLO BIG WORLD',
            'typed_shout' => 'HELLO BIG WORLD',
            'badge' => 'gold',
            'word_count' => 3,
            'title_length' => 15,
            'ticket' => 1.5,
            'codes' => ['a', 'b'],
            'arrow_doc' => 3,
            'shout_static' => 'HELLO BIG WORLD',
        ]);
    });

    it('declines a chain through a macro whose class lacks the next method', function () {
        expect(publishedResourceKeyTypes(MacroShadowingChainResource::class, MacroShadowedPost::class))
            ->toBe(['moment_year' => 'unknown'])
            ->and(fn () => new MacroShadowingChainResource(macroShadowedPost())->resolve(Request::create('/')))
            ->toThrow(BadMethodCallException::class, 'Stringable::format');
    });

    it('reads a macro closure where it is declared, not where it is called', function () {
        expect(publishedResourceKeyTypes(MacroScopeLeakResource::class, MacroShadowedPost::class))->toBe([
            'excl' => 'string',
            'arrow_excl' => 'string',
            'again' => 'string',
            'with_arg' => 'unknown',
        ])->and(sentByMacroResource(new MacroScopeLeakResource(macroShadowedPost())))->toBe([
            'excl' => '!',
            'arrow_excl' => '!',
            'again' => 'done',
            'with_arg' => 5,
        ]);
    });

    it('types a collection\'s bare call from a macro, whose body reads the items as the collection does', function () {
        $published = publishedResourceKeyTypes(MacroShadowingCollection::class, MacroShadowedPost::class);
        $sent = sentByMacroResource(new MacroShadowingCollection(collect([macroShadowedPost()])));

        expect($published['label'] ?? null)->toBe('string')
            ->and($published['held_rows'] ?? null)->toBe($published['held'] ?? null)->not->toBe('unknown')
            ->and($sent['label'])->toBe('macro-label')
            ->and($sent['held_rows'])->toBe($sent['held']);
    });

    it('types a macro from its own callable, and never one named like a method the subject declares', function () {
        $scope = new AnalysisScope(new ReflectionClass(MacroShadowingResource::class), MacroShadowedPost::class);
        $macros = resolve(MacroCallResolver::class);

        // PHP runs the declared toArray(), so this macro is never reached.
        JsonResource::macro('toArray', fn (): int => 1);

        expect($macros->runsMacro($scope->subjectReflection, 'label'))->toBeTrue()
            ->and($macros->runsMacro($scope->subjectReflection, 'toArray'))->toBeFalse()
            ->and($macros->resolve($scope, 'ticket'))->toMatchArray(['type' => 'number'])
            ->and($macros->resolve($scope, 'wordCount'))->toBeNull()
            ->and($macros->resolve($scope, 'toArray'))->toBeNull();
    });
});

describe('with no resource macro registered', function () {
    it('forwards a static-syntax call to an undeclared method as __call() does', function () {
        $published = publishedResourceKeyTypes(MacroShadowingResource::class, MacroShadowedPost::class);
        // A final class's `static::` is its `self::`, which Pint spells, so `$this::` stands in for the late binding.
        $resource = new class(macroShadowedPost()) extends JsonResource
        {
            /** @return array<string, mixed> */
            public function toArray(Request $request): array
            {
                return ['label_this_static' => $this::label(), 'label_self' => self::label()];
            }
        };

        expect($published['label_static'] ?? null)->toBe('number')
            ->and($published['label_self'] ?? null)->toBe('number')
            ->and(sentByMacroResource($resource))->toBe(['label_this_static' => 7, 'label_self' => 7]);
    });

    it('runs a method the resource declares before a macro registered under its name', function () {
        $without = publishedResourceKeyTypes(CategoryResource::class, Category::class);

        JsonResource::macro('when', fn (): int => 1);
        JsonResource::macro('whenLoaded', fn (): int => 1);
        resolve(AnalysisMemo::class)->reset();

        expect(publishedResourceKeyTypes(CategoryResource::class, Category::class))->toBe($without);
    });

    it('reflects each kind of callable a macro can be', function (mixed $macro, string $reflection, string $name) {
        $reflected = resolve(MacroCallResolver::class)->reflect($macro);

        expect($reflected)->toBeInstanceOf($reflection)
            ->and($reflected?->getName())->toStartWith($name);
    })->with([
        'a closure' => [fn (): int => 1, ReflectionFunction::class, '{closure'],
        'an invokable object' => [new TicketMacro, ReflectionMethod::class, '__invoke'],
        'a class and method' => [[Str::class, 'upper'], ReflectionMethod::class, 'upper'],
        'a class::method string' => [Str::class.'::upper', ReflectionMethod::class, 'upper'],
        'a function name' => ['strtoupper', ReflectionFunction::class, 'strtoupper'],
    ]);

    it('reflects nothing a macro cannot call', function (mixed $macro) {
        expect(resolve(MacroCallResolver::class)->reflect($macro))->toBeNull();
    })->with([
        'null' => [null],
        'a missing function' => ['noSuchFunctionAnywhere'],
        'a missing method' => [[Str::class, 'noSuchMethod']],
        'an object that is not invokable' => [new stdClass],
    ]);
});

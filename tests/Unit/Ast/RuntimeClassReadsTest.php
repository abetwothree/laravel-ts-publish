<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BaseWrittenTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BuiltInstanceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LateReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NumberTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ParameterTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SilverTierResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StaticSyntaxResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StaticSyntaxPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverBaseDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverChildDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverHolderBaseDto;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverHolderChildDto;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use Workbench\App\Http\Resources\NoticeResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PanelPayload;

/** Parse one `$this->m()` call written as a statement. */
function runtimeClassCall(string $php): Expr
{
    $statement = new AstParser()->parseSource('<?php '.$php.';')[0];
    assert($statement instanceof Expression);

    return $statement->expr;
}

/**
 * What StaticSyntaxResource sends over a fresh Post, its inherited `toArray()` run as PHP runs it.
 *
 * @return array<string, mixed>
 */
function staticSyntaxResponse(): array
{
    return (new StaticSyntaxResource(new Post))->resolve(request());
}

it('reads a method an inherited body calls on the runtime class', function () {
    expect(analyzedKeyTypes(PanelPayload::class))->toMatchArray(['title' => 'string', 'child_only' => 'boolean'])
        ->and(analyzedKeyTypes(PanelPayload::class))->not->toHaveKey('base_only')
        ->and(json_encode(new PanelPayload()->toArray()))->toContain('"title":"x","child_only":true');
});

it('walks the with() and the spread helper the runtime class declares', function () {
    expect(analyzedKeyTypes(NoticeResource::class))->toMatchArray([
        'headline' => 'string', 'child_only' => 'boolean', 'meta' => 'number',
    ])->and(analyzedKeyTypes(NoticeResource::class))->not->toHaveKey('base_only')
        ->and((new NoticeResource(null))->resolve(request()))->toMatchArray(['headline' => 'x', 'child_only' => true, 'meta' => 1])
        ->and((new NoticeResource(null))->resolve(request()))->not->toHaveKey('base_only');
});

it('keeps a member the declaring class declares private', function () {
    expect(analyzedKeyTypes(PanelPayload::class)['weight'])->toBe('number')
        ->and(new PanelPayload()->toArray()['weight'])->toBe(1);
});

it('types the receiver an inherited body\'s $this->m() returns as the runtime class runs it', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverBaseDto::class));
    $scope->runtimeReflection = new ReflectionClass(ReceiverChildDto::class);
    $resolver = resolve(ReceiverClassResolver::class);

    expect($resolver->resolve(runtimeClassCall('$this->fresh()'), $scope)?->classes)->toBe([ReceiverChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->copy()'), $scope)?->classes)->toBe([ReceiverBaseDto::class])
        ->and(new ReceiverChildDto()->fresh())->toBeInstanceOf(ReceiverChildDto::class)
        ->and(new ReceiverChildDto()->copy())->not->toBeInstanceOf(ReceiverChildDto::class);
});

it('spreads the helper a `self::` call in an inherited body names, not the runtime class\'s override', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('base_helper', 'number')
        ->not->toHaveKey('child_helper')
        ->and(staticSyntaxResponse())->toHaveKey('base_helper', 1)->not->toHaveKey('child_helper');
});

it('reads a method on the `new self` an inherited body builds, not on the runtime class', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('built', '{ base_label: number }')
        ->and(staticSyntaxResponse()['built'])->toBe(['base_label' => 1]);
});

it('forwards a `self::` call the declaring class lacks to the model, though the runtime class declares it', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('forwarded', 'boolean')
        ->and(staticSyntaxResponse()['forwarded'])->toBeFalse();
});

it('reads a `$this::` call on the runtime class, where no private member of the declaring class answers', function () {
    expect(analyzedKeyTypes(StaticSyntaxPayload::class))->toBe(['static_syntax' => 'string', 'arrow' => 'number'])
        ->and(new StaticSyntaxPayload()->toArray())->toBe(['static_syntax' => 'x', 'arrow' => 1]);
});

it('reads a `static::` call in an inherited body on the runtime class', function () {
    expect(publishedResourceKeyTypes(StaticSyntaxResource::class, Post::class))->toHaveKey('child_late', 'boolean')
        ->toHaveKey('late', 'string')
        ->not->toHaveKey('base_late')
        ->and(staticSyntaxResponse())->toHaveKey('child_late', true)->toHaveKey('late', 'child')->not->toHaveKey('base_late');
});

it('reads static:: and an untyped default in an inherited body as the runtime class', function () {
    expect(analyzedKeyTypes(PanelPayload::class))->toMatchArray([
        'label' => 'number', 'tier' => 'number', 'secret' => 'string', 'late' => 'string',
    ])->and(analyzedKeyTypes(NoticeResource::class))->toMatchArray(['label' => 'number', 'tier' => 'number'])
        ->and(json_encode(new PanelPayload()->toArray()))->toContain('"label":7,"tier":3,"secret":"x"');
});

it('reads static:: and $this->prop in an inherited resource body as the runtime class sends them', function () {
    expect((new NoticeResource(null))->resolve(request()))->toMatchArray(['label' => 7, 'tier' => 3]);
});

it('reads a method on an instance an inherited body builds with $this as the class it builds', function () {
    expect(publishedResourceKeyTypes(BuiltInstanceResource::class, Post::class))->toMatchArray([
        'built' => '{ base_part: number }',
        'late_built' => '{ child_part: boolean }',
        'late_made' => '{ child_part: boolean }',
    ])->and((new BuiltInstanceResource(new Post))->resolve(request()))->toMatchArray([
        'built' => ['base_part' => 1],
        'late_built' => ['child_part' => true],
        'late_made' => ['child_part' => true],
    ]);
});

it('reads a static:: key and a declared property of a model-backed inherited body on the runtime class', function () {
    expect(publishedResourceKeyTypes(LateReadsResource::class, Post::class))
        ->toMatchArray(['child_key' => 'number', 'tier' => 'number', 'secret' => 'string'])
        ->and((new LateReadsResource(new Post))->resolve(request()))->toMatchArray(['child_key' => 1, 'tier' => 3, 'secret' => 'x']);
});

it('reads a property only the runtime class declares, and `new static` or static::make() as a value, on that class', function () {
    $sent = (new LateReadsResource(new Post))->resolve(request());

    expect(publishedResourceKeyTypes(LateReadsResource::class, Post::class))->toMatchArray([
        'badge' => 'number', 'again' => 'LateReadsResource', 'made' => 'LateReadsResource',
    ])->and($sent['badge'])->toBe(5)
        ->and($sent['again'])->toBeInstanceOf(LateReadsResource::class)
        ->and($sent['made'])->toBeInstanceOf(LateReadsResource::class);
});

it('types `new static` and a `$this->prop` receiver in an inherited body as the runtime class declares them', function () {
    $scope = new AnalysisScope(new ReflectionClass(ReceiverHolderBaseDto::class));
    $scope->runtimeReflection = new ReflectionClass(ReceiverHolderChildDto::class);
    $resolver = resolve(ReceiverClassResolver::class);

    expect($resolver->resolve(runtimeClassCall('new static'), $scope)?->classes)->toBe([ReceiverHolderChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->held'), $scope)?->classes)->toBe([ReceiverChildDto::class])
        ->and($resolver->resolve(runtimeClassCall('$this->own'), $scope)?->classes)->toBe([ReceiverBaseDto::class])
        ->and(new ReceiverHolderChildDto()->fresh())->toBeInstanceOf(ReceiverHolderChildDto::class)
        ->and(new ReceiverHolderChildDto()->heldClass())->toBe(ReceiverChildDto::class)
        ->and(new ReceiverHolderChildDto()->ownClass())->toBe(ReceiverBaseDto::class);
});

/**
 * What a BaseWrittenTierResource subclass sends over a fresh Post, its inherited `toArray()` run as PHP runs it.
 *
 * @return array<string, mixed>
 */
function writtenTierResponse(BaseWrittenTierResource $resource): array
{
    return $resource->resolve(request());
}

it('types an untyped property as its default joined with every literal a method the runtime class runs writes', function (
    BaseWrittenTierResource $resource,
    string $type,
    mixed $default,
    Closure $write,
    mixed $written,
) {
    expect(publishedResourceKeyTypes($resource::class, Post::class)['tier'])->toBe($type)
        ->and(writtenTierResponse($resource)['tier'])->toBe($default)
        ->and(writtenTierResponse($write($resource))['tier'])->toBe($written);
})->with([
    'a string literal' => [fn () => new SilverTierResource(new Post), 'string', 'gold', fn (SilverTierResource $r) => $r->promote(), 'silver'],
    'an int literal' => [fn () => new NumberTierResource(new Post), 'string | number', 'gold', fn (NumberTierResource $r) => $r->promote(), 5],
    'a parameter' => [
        fn () => new ParameterTierResource(new Post), 'unknown', 'gold', fn (ParameterTierResource $r) => $r->promote(['x' => 1]), ['x' => 1],
    ],
]);

it('types an untyped property a class writes itself as its default joined with the literal written', function () {
    expect(publishedResourceKeyTypes(BaseWrittenTierResource::class, Post::class))->toBe(['tier' => 'string', 'level' => 'number | string'])
        ->and(writtenTierResponse(new BaseWrittenTierResource(new Post)))->toBe(['tier' => 'gold', 'level' => 1])
        ->and(writtenTierResponse(new BaseWrittenTierResource(new Post)->maxLevel()))->toBe(['tier' => 'gold', 'level' => 'max']);
});

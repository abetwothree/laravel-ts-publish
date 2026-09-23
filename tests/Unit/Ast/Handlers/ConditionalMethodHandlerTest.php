<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Http\Resources\ArtistResource;
use Workbench\App\Http\Resources\ConditionalDefaultsResource;
use Workbench\App\Http\Resources\ImageResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ReviewResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\VenueResource;
use Workbench\App\Http\Resources\WhenHasValueResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Artist;
use Workbench\App\Models\ArtistReview;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Review;
use Workbench\App\Models\User;
use Workbench\App\Models\Venue;
use Workbench\App\Models\VenueReview;
use Workbench\Crm\Models\User as CrmUser;

/**
 * An AnalysisScope for tests that don't need a real backing model.
 */
function conditionalMethodHandlerScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ConditionalDefaultsResource::class));
}

/**
 * Resolve one expression through the full resource profile over a Post, with `$local` bound as a plain local.
 *
 * @return array<string, mixed>
 */
function conditionalMethodHandlerResolveOnPost(string $php): array
{
    $parse = fn (string $source): Expr => new AstParser()->parseSource('<?php '.$source.';')[0]->expr;
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->localVarBindings['local'] = $parse('$this->author');

    return new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope)
        ->resolve($parse($php));
}

/**
 * An engine that fails the test if a handler calls back into it, proving the handler declined —
 * or resolved a bare/model-typed shape — without resolving any sub-expression.
 */
function conditionalMethodHandlerThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

/**
 * A stub engine resolving each distinct expression instance to its own canned result, keyed by
 * object identity — mirrors ClosureHandlerArmStubEngine's convention for the same reason.
 */
final class ConditionalMethodHandlerArmStubEngine implements ExpressionEngine
{
    /** @param list<array{0: Expr, 1: array<string, mixed>}> $arms */
    public function __construct(private array $arms) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        foreach ($this->arms as [$candidate, $result]) {
            if ($candidate === $expr) {
                return $result;
            }
        }

        throw new RuntimeException('Unexpected expression passed to ConditionalMethodHandlerArmStubEngine');
    }

    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

/**
 * An engine that records the scope's closureRelationModelClass/varModelBindings/
 * varCollectionBindings at the moment it is called, proving whenLoaded()'s bindings are live only
 * for the closure body's own resolution — and, for a to-many relation, that varModelBindings is
 * never written at all (the documented asymmetry: binding the element model to a bare param would
 * resolve it to a wrong-but-plausible singular type).
 */
final class ConditionalMethodHandlerScopeSpyEngine implements ExpressionEngine
{
    public bool $wasCalled = false;

    public ?string $relationModelClassDuringCall = null;

    /** @var array<string, class-string> */
    public array $varModelBindingsDuringCall = [];

    /** @var array<string, array{type: string, modelFqcn: class-string}> */
    public array $varCollectionBindingsDuringCall = [];

    /** @param array<string, mixed> $result */
    public function __construct(private AnalysisScope $scope, private array $result) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        $this->wasCalled = true;
        $this->relationModelClassDuringCall = $this->scope->closureRelationModelClass;
        $this->varModelBindingsDuringCall = $this->scope->varModelBindings;
        $this->varCollectionBindingsDuringCall = $this->scope->varCollectionBindings;

        return $this->result;
    }

    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

// ConditionalMethodHandler — pinned resolutions

it('resolves whenLoaded() on a singular relation as optional and model-typed, carrying modelFqcn', function () {
    $expr = new MethodCall(new Variable('this'), 'whenLoaded', [new Arg(new String_('profile'))]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, conditionalMethodHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'Profile | null',
        'optional' => true,
        'modelFqcn' => Profile::class,
    ]);
});

it('resolves when(condition, value, default) with an explicit default as required and unioned', function () {
    $condition = new BinaryOp\Greater(new PropertyFetch(new Variable('this'), 'id'), new Int_(0));
    $valueExpr = new Variable('valueArm');
    $defaultExpr = new Int_(0);
    $expr = new MethodCall(new Variable('this'), 'when', [
        new Arg($condition),
        new Arg($valueExpr),
        new Arg($defaultExpr),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$valueExpr, ['type' => 'string', 'optional' => false]],
        [$defaultExpr, ['type' => 'number', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'string | number', 'optional' => false]);
});

it('resolves whenCounted() with no default as an optional number', function () {
    $expr = new MethodCall(new Variable('this'), 'whenCounted', [new Arg(new String_('posts'))]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), conditionalMethodHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => true]);
});

it('resolves whenCounted() with an explicit default as required and unioned', function () {
    $defaultExpr = new String_('none');
    $expr = new MethodCall(new Variable('this'), 'whenCounted', [
        new Arg(new String_('user')),
        new Arg(new ConstFetch(new Name('null'))),
        new Arg($defaultExpr),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'number | string', 'optional' => false]);
});

it('binds whenLoaded()\'s closure param to the related model only for the closure body, then restores scope', function () {
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);
    $scope->closureRelationModelClass = null;
    $scope->varModelBindings = ['unrelated' => Address::class];
    $scope->varCollectionBindings = [];

    $bodyExpr = new Variable('profile');
    $param = new Param(new Variable('profile'));
    $closure = new ArrowFunction(['params' => [$param], 'expr' => $bodyExpr]);
    $expr = new MethodCall(new Variable('this'), 'whenLoaded', [
        new Arg(new String_('profile')),
        new Arg($closure),
    ]);
    $engine = new ConditionalMethodHandlerScopeSpyEngine($scope, ['type' => 'string', 'optional' => false]);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, $engine);

    expect($engine->wasCalled)->toBeTrue()
        ->and($engine->relationModelClassDuringCall)->toBe(Profile::class)
        ->and($engine->varModelBindingsDuringCall)->toBe(['unrelated' => Address::class, 'profile' => Profile::class])
        ->and($engine->varCollectionBindingsDuringCall)->toBe([])
        ->and($scope->closureRelationModelClass)->toBeNull()
        ->and($scope->varModelBindings)->toBe(['unrelated' => Address::class])
        ->and($scope->varCollectionBindings)->toBe([])
        ->and($result)->toBe(['type' => 'string', 'optional' => true]);
});

it('binds a to-many whenLoaded()\'s closure param to the collection type, never varModelBindings, then restores scope', function () {
    // Mirrors the singular-relation test above, on a to-many relation (User::posts(), a HasMany).
    // The asymmetry this pins: the element model is never bound to the bare param — only the whole
    // collection type is — because a wrong-but-plausible singular binding would be worse than none.
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);
    $scope->closureRelationModelClass = null;
    $scope->varModelBindings = ['unrelated' => Address::class];
    $scope->varCollectionBindings = [];

    $bodyExpr = new Variable('posts');
    $param = new Param(new Variable('posts'));
    $closure = new ArrowFunction(['params' => [$param], 'expr' => $bodyExpr]);
    $expr = new MethodCall(new Variable('this'), 'whenLoaded', [
        new Arg(new String_('posts')),
        new Arg($closure),
    ]);
    $engine = new ConditionalMethodHandlerScopeSpyEngine($scope, ['type' => 'string', 'optional' => false]);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, $engine);

    expect($engine->wasCalled)->toBeTrue()
        ->and($engine->relationModelClassDuringCall)->toBe(Post::class)
        ->and($engine->varModelBindingsDuringCall)->toBe(['unrelated' => Address::class])
        ->and($engine->varCollectionBindingsDuringCall)->toBe([
            'posts' => ['type' => 'Post[]', 'modelFqcn' => Post::class],
        ])
        ->and($scope->closureRelationModelClass)->toBeNull()
        ->and($scope->varModelBindings)->toBe(['unrelated' => Address::class])
        ->and($scope->varCollectionBindings)->toBe([])
        ->and($result)->toBe(['type' => 'string', 'optional' => true]);
});

// ConditionalMethodHandler — declines

it('declines a different $this-> method name without calling the engine', function () {
    $expr = new MethodCall(new Variable('this'), 'somethingElse', [new Arg(new String_('x'))]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), conditionalMethodHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('declines a conditional-family method name called on a non-$this receiver', function () {
    $expr = new MethodCall(new Variable('foo'), 'whenLoaded', [new Arg(new String_('profile'))]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), conditionalMethodHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('declines $this->toResource(), a later slice\'s guard, without calling the engine', function () {
    // The regression this contract exists to prevent: a name this handler doesn't own shadowing a
    // later guard. toResource()/merge() are both real $this-> methods on JsonResource that a wider
    // match could accidentally swallow.
    $expr = new MethodCall(new Variable('this'), 'toResource');

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), conditionalMethodHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('declines $this->merge(), a later slice\'s guard, without calling the engine', function () {
    $expr = new MethodCall(new Variable('this'), 'merge', [new Arg(new Variable('x'))]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), conditionalMethodHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// ConditionalMethodHandler — named arguments. PHP binds them by name, then Laravel tests func_num_args().

it('reads whenNotNull($value, default: …) as a real default, required and unioned', function () {
    $valueExpr = new PropertyFetch(new Variable('this'), 'full_address');
    $defaultExpr = new Int_(0);
    $expr = new MethodCall(new Variable('this'), 'whenNotNull', [
        new Arg($valueExpr),
        new Arg($defaultExpr, name: new Identifier('default')),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$valueExpr, ['type' => 'string | null', 'optional' => false]],
        [$defaultExpr, ['type' => 'number', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'string | number', 'optional' => false]);
});

it('reads when() with every argument named, written in reverse order', function () {
    $condition = new BinaryOp\Greater(new PropertyFetch(new Variable('this'), 'id'), new Int_(0));
    $valueExpr = new Variable('valueArm');
    $defaultExpr = new Int_(0);
    $expr = new MethodCall(new Variable('this'), 'when', [
        new Arg($defaultExpr, name: new Identifier('default')),
        new Arg($valueExpr, name: new Identifier('value')),
        new Arg($condition, name: new Identifier('condition')),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$valueExpr, ['type' => 'string', 'optional' => false]],
        [$defaultExpr, ['type' => 'number', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'string | number', 'optional' => false]);
});

// whenLoaded('rel', default: …) is three arguments to Laravel, which then swaps the null $value for the
// identity closure — so the loaded arm is still the relation, and the model import travels with it.
it('types whenLoaded(relation, default: …) from the relation, required, with the default unioned in', function () {
    $defaultExpr = new ConstFetch(new Name('null'));
    $expr = new MethodCall(new Variable('this'), 'whenLoaded', [
        new Arg(new String_('profile')),
        new Arg($defaultExpr, name: new Identifier('default')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'null', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, $engine);

    expect($result)->toMatchArray(['type' => 'Profile | null', 'optional' => false, 'embeddedModelFqcns' => [Profile::class]]);
});

it('reads whenCounted(default: …, relationship: …) written out of order as required', function () {
    $defaultExpr = new Int_(0);
    $expr = new MethodCall(new Variable('this'), 'whenCounted', [
        new Arg($defaultExpr, name: new Identifier('default')),
        new Arg(new String_('posts'), name: new Identifier('relationship')),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'number', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('reads whenAggregated(…, default: …) at the family\'s deepest default position', function () {
    $defaultExpr = new String_('none');
    $expr = new MethodCall(new Variable('this'), 'whenAggregated', [
        new Arg(new String_('items')),
        new Arg(new String_('price')),
        new Arg(new String_('sum')),
        new Arg($defaultExpr, name: new Identifier('default')),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'number | string', 'optional' => false]);
});

// whenHas('attr', default: …) skips $value: Laravel counts three arguments and evaluates value(null, …),
// so the present arm is null — the attribute's own type never surfaces.
it('types whenHas(attribute, default: …) with the value skipped as null unioned with the default', function () {
    $defaultExpr = new String_('none');
    $expr = new MethodCall(new Variable('this'), 'whenHas', [
        new Arg(new String_('name')),
        new Arg($defaultExpr, name: new Identifier('default')),
    ]);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine);

    expect($result)->toBe(['type' => 'string | null', 'optional' => false]);
});

// whenAppended('attr', default: …) skips $value the same way whenHas() does: Laravel counts three
// arguments and evaluates value(null, …), so the present arm is null, not the appended accessor's type.
it('types whenAppended(attribute, default: …) with the value skipped as null unioned with the default', function () {
    $defaultExpr = new String_('none');
    $expr = new MethodCall(new Variable('this'), 'whenAppended', [
        new Arg(new String_('name')),
        new Arg($defaultExpr, name: new Identifier('default')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBe(['type' => 'string | null', 'optional' => false]);
});

// whenExistsLoaded('rel', default: …) skips $value: unlike whenLoaded(), there is no identity-closure
// swap here, so Laravel evaluates value(null, …) and the present arm is null, not the exists flag.
it('types whenExistsLoaded(relationship, default: …) with the value skipped as null unioned with the default', function () {
    $defaultExpr = new String_('none');
    $expr = new MethodCall(new Variable('this'), 'whenExistsLoaded', [
        new Arg(new String_('posts')),
        new Arg($defaultExpr, name: new Identifier('default')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBe(['type' => 'string | null', 'optional' => false]);
});

// The three tests above all write `default:` by name, which skips $value and reaches valueSkipped()
// through passedCount(). A positionally written literal null binds $value instead, so only
// isNullConstFetch() can tell that the arm is null rather than the attribute.
it('types whenHas(attribute, null, default) written positionally as null unioned with the default', function () {
    $defaultExpr = new String_('none');
    $expr = new MethodCall(new Variable('this'), 'whenHas', [
        new Arg(new String_('name')),
        new Arg(new ConstFetch(new Name('null'))),
        new Arg($defaultExpr),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);
    $engine = new ConditionalMethodHandlerArmStubEngine([
        [$defaultExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBe(['type' => 'string | null', 'optional' => false]);
});

// The literal-null branch reads named('value') alone, so unlike the passedCount() branch it must not bail
// on a spread: two positional arguments already make func_num_args() >= 2 whatever $rest holds.
it('types a literal null value as null even when a spread follows it', function () {
    $expr = new MethodCall(new Variable('this'), 'whenHas', [
        new Arg(new String_('name')),
        new Arg(new ConstFetch(new Name('null'))),
        new Arg(new Variable('rest'), unpack: true),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, conditionalMethodHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'null', 'optional' => true]);
});

it('still treats a spread at the default position as no default', function () {
    $expr = new MethodCall(new Variable('this'), 'whenCounted', [
        new Arg(new String_('posts')),
        new Arg(new Array_([]), unpack: true),
    ]);

    $result = (new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), conditionalMethodHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => true]);
});

// valueSkipped() reads the same unreliable passedCount() hasExplicitDefaultArg() already bails on for a
// spread, so it must bail too: a spread before a named default must not be misread as a skipped $value.
it('does not report a skipped value when a spread precedes a named default', function () {
    $expr = new MethodCall(new Variable('this'), 'whenHas', [
        new Arg(new String_('name')),
        new Arg(new Variable('rest'), unpack: true),
        new Arg(new String_('x'), name: new Identifier('default')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(UserResource::class), User::class);

    $result = (new ConditionalMethodHandler)->resolve($expr, $scope, conditionalMethodHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => true]);
});

describe('positional null value arm', function () {
    test('whenHas / whenAppended / whenExistsLoaded with a literal null value type the arm as null', function () {
        $analysis = resolve(AstEngine::class)->analyzeMethod(ConditionalDefaultsResource::class);
        $types = array_column($analysis->properties, 'type', 'name');

        expect($types['has_with_null'])->toBe('number | null')
            ->and($types['appended_with_null'])->toBe('number | null')
            ->and($types['exists_with_default'])->toBe('string | null');
    });
});

// Laravel ends all three in value($value, …), so a resolvable value argument — not the named
// attribute — is what the property carries.
describe('value argument types the arm', function () {
    test('whenHas, whenAppended and whenExistsLoaded type from the value Laravel returns', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(WhenHasValueResource::class), Post::class)->analyze()->properties)->keyBy('name');

        expect($props->map->type->all())->toMatchArray([
            'has_title' => 'boolean',
            'title_length' => 'number',
            'title_passthrough' => 'string',
            'appended_label' => 'string',
            'comments_flag' => 'string',
        ])->and($props->every(fn (array $p): bool => $p['optional']))->toBeTrue();
    });

    // comments_flag returns string literals in both arms, so it holds whatever $exists binds to — or
    // nothing at all. Only a closure that returns the parameter makes the flag name load-bearing: a
    // wrong name or a dropped binding leaves this `unknown` instead of the flag's own boolean.
    test('whenExistsLoaded binds its closure parameter to the generated {relation}_exists flag', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(WhenHasValueResource::class), Post::class)->analyze()->properties)->keyBy('name');

        expect($props['comments_exists_flag']['type'])->toBe('boolean')
            ->and($props['comments_exists_flag']['optional'])->toBeTrue();
    });

    // The fallback the value rule must never break: an unresolvable value leaves the attribute's own
    // type standing rather than publishing a fresh `unknown`. json_decode() returns mixed, so the
    // closure body resolves to unknown and `title`'s own `string` has to survive.
    test('an unresolvable value keeps the named attribute type instead of becoming unknown', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(WhenHasValueResource::class), Post::class)->analyze()->properties)->keyBy('name');

        expect($props['title_unresolvable']['type'])->toBe('string')
            ->and($props['title_unresolvable']['optional'])->toBeTrue();
    });
});

test('a morph union closure param binds every target and toResource unions their resources', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Venue::class, Artist::class, Review::class, VenueReview::class, ArtistReview::class]);

    $analysis = new ResourceAstAnalyzer(new ReflectionClass(ReviewResource::class), Review::class)->analyze();
    $props = collect($analysis->properties)->keyBy('name');

    expect($props['reviewable']['type'])->toBe('ArtistResource | VenueResource')
        ->and($props['reviewable']['optional'])->toBeTrue()
        ->and($props['reviewable_name']['type'])->toBe('string')
        ->and(array_keys($analysis->nestedResources))->toContain(ArtistResource::class, VenueResource::class);
});

// transform() calls $callback($value): the parameter holds the value, so it takes whatever that value is bound to.
it('binds a transform() callback parameter to the value its call passes', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'a to-one whenLoaded variable, chain' => [
        '$this->whenLoaded("author", fn ($author) => $this->transform($author, fn ($author) => $author->profile?->bio))',
        'string | null',
    ],
    'a to-one whenLoaded variable, bare' => ['$this->whenLoaded("author", fn ($author) => $this->transform($author, fn ($author) => $author))', 'User'],
    'a relation-chain map variable' => [
        '$this->comments->map(fn ($c) => $this->transform($c, fn ($c) => ["id" => $c->id, "who" => $c->user?->name]))',
        '({ id: number; who: string | null })[]',
    ],
    'a to-many whenLoaded variable' => [
        '$this->whenLoaded("comments", fn ($comments) => $this->transform($comments, fn ($comments) => $comments))',
        'Comment[]',
    ],
    'a variable under another parameter name' => [
        '$this->whenLoaded("author", fn ($author) => $this->transform($author, fn ($a) => $a->profile?->bio))',
        'string | null',
    ],
    'a plain local' => ['$this->transform($local, fn ($local) => $local->email)', 'string'],
    'a model read through the resource' => ['$this->transform($this->resource->author, fn ($a) => $a->email)', 'string'],
    'a comparison, which passes a boolean' => ['$this->transform($this->title !== null, fn ($b) => $b)', 'boolean'],
]);

// Without the claim, ClosureHandler releases the name the value argument just bound, and the key loses its type.
it('binds a value closure parameter to the attribute whenHas() and whenExistsLoaded() pass', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'whenHas()' => ['$this->whenHas("title", fn ($t) => ["t" => $t])', '{ t: string }'],
    'whenExistsLoaded()' => ['$this->whenExistsLoaded("comments", fn ($e) => ["e" => $e])', '{ e: boolean }'],
]);

// Each writer binds a parameter to what Laravel calls its closure with: a variadic one collects its arguments into a
// list, and an optional one the call passes nothing holds its default.
it('binds each conditional closure parameter to what Laravel passes it', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'whenLoaded() to-one, variadic' => ['$this->whenLoaded("author", fn (...$a) => $a)', 'User[]'],
    'whenLoaded() to-many, variadic' => ['$this->whenLoaded("comments", fn (...$c) => $c)', 'Comment[][]'],
    'when(), variadic' => ['$this->when($this->title, fn (...$t) => $t)', 'never[]'],
    'whenHas(), variadic' => ['$this->whenHas("title", fn (...$t) => $t)', 'string[]'],
    'whenExistsLoaded(), variadic' => ['$this->whenExistsLoaded("comments", fn (...$e) => $e)', 'boolean[]'],
    'transform(), variadic' => ['$this->transform($this->title, fn (...$t) => $t)', 'string[]'],
    'transform(), variadic, passed an optional value' => ['$this->transform($this->whenHas("title"), fn (...$t) => ["k" => $t])', '{ k: string[] }'],
    'when(), optional null' => ['$this->when($this->title, fn ($t = null) => $t)', 'null'],
    'when(), optional int' => ['$this->when($this->title, fn ($t = 5) => $t)', 'number'],
    'when() on a null test, optional null' => ['$this->when($this->rating !== null, fn ($r = null) => $r)', 'null'],
    'unless(), optional null' => ['$this->unless($this->title, fn ($t = null) => $t)', 'null'],
    'when() on an enum, optional null' => ['$this->when($this->status, fn ($s = null) => $s)', 'null'],
    'when(), required, whose call throws' => ['$this->when($this->title, fn ($t) => $t)', 'string'],
    'whenAppended(), optional int' => ['$this->whenAppended("excerpt", fn ($e = 5) => $e)', 'number'],
    'whenLoaded() default, optional null' => ['$this->whenLoaded("author", fn ($a) => $a->email, fn ($local = null) => $local)', 'string | null'],
    'when() default, optional null' => ['$this->when($this->title, 1, fn ($local = null) => $local)', 'number | null'],
    'transform() default, passed the blank value' => ['$this->transform($this->rating, fn ($r) => "x", fn ($r) => $r)', 'string | number | null'],
    'whenCounted() closure, passed the count' => ['$this->whenCounted("comments", fn ($n) => ["n" => $n])', '{ n: number }'],
    'whenCounted() closure, comparing the count' => ['$this->whenCounted("comments", fn ($n) => $n > 3)', 'boolean'],
    'whenCounted() closure, ignoring the count' => ['$this->whenCounted("comments", fn ($n) => "x")', 'string'],
    'whenAggregated() closure, ignoring the aggregate' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => "x")', 'string'],
    'whenAggregated() closure, returning the untyped aggregate' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => $m)', 'number'],
    'whenAggregated() closure, binding nothing' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => ["m" => $m])', '{ m: unknown }'],
    'whenLoaded() second parameter, optional int' => ['$this->whenLoaded("author", fn ($a, $b = 5) => $b)', 'number'],
    'transform() callback second parameter, optional int' => ['$this->transform($this->title, fn ($t, $u = 5) => $u)', 'number'],
    'transform() default, variadic, passed the blank value' => ['$this->transform($this->rating, fn ($r) => "x", fn (...$r) => $r)', 'string | (number | null)[]'],
    'transform() default, passed a nullable model' => [
        '$this->transform($this->resource->author->profile, fn ($p) => 1, fn ($p) => $p)',
        'number | Profile | null',
    ],
]);

// The package publishes an aggregate as number whatever its column; narrowing it by driver and cast is still open.
it('publishes whenAggregated()\'s aggregate as number', function (string $php) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe('number');
})->with([
    'max()' => ['$this->whenAggregated("comments", "created_at", "max")'],
    'sum(), a null value' => ['$this->whenAggregated("comments", "id", "sum", null)'],
    'count()' => ['$this->whenAggregated("comments", "id", "count")'],
]);

// A parameter the call passes nothing holds its default, which PHP evaluates as a constant expression: a list literal
// is a list, never the record the engine types an array literal as, and one the evaluator cannot read binds nothing.
it('binds a parameter default to the value it evaluates to', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'when(), a list' => ['$this->when($this->title, fn ($t = [1, 2]) => $t)', 'number[]'],
    'when(), a mixed list' => ['$this->when($this->title, fn ($t = [1, "a"]) => $t)', '(number | string)[]'],
    'when(), a nested list' => ['$this->when($this->title, fn ($t = [[1, 2], [3]]) => $t)', 'number[][]'],
    'when(), a record holding a list' => ['$this->when($this->title, fn ($t = ["a" => [1, 2]]) => $t)', '{ a: number[] }'],
    'when(), a spread and a ternary' => ['$this->when($this->title, fn ($t = [...[1], true ? 2 : "x"]) => $t)', 'number[]'],
    'when(), a class constant' => ['$this->when($this->title, fn ($t = [self::class]) => $t)', 'string[]'],
    'whenHas() default closure, a list' => ['$this->whenHas("no_such_attr", 1, fn ($d = [1]) => $d)', 'number | number[]'],
    'transform() callback, a later list' => ['$this->transform($this->title, fn ($t, $u = [1]) => $u)', 'number[]'],
    'whenLoaded(), a later list' => ['$this->whenLoaded("author", fn ($a, $u = ["x"]) => $u)', 'string[]'],
    'when(), an int-keyed record a resource re-indexes' => ['$this->when($this->title, fn ($t = [1 => "a"]) => $t)', 'unknown'],
    'when(), a list the evaluator cannot read' => ['$this->when($this->title, fn ($t = [PHP_INT_SIZE]) => $t)', 'unknown'],
    'when(), a record the evaluator cannot read' => ['$this->when($this->title, fn ($t = ["a" => PHP_INT_SIZE]) => $t)', '{ a: unknown }'],
    'when(), a record holding a list the evaluator cannot read' => [
        '$this->when($this->title, fn ($t = ["a" => [PHP_INT_SIZE]]) => $t)',
        'unknown',
    ],
    'when(), an int-like key the evaluator cannot read' => ['$this->when($this->title, fn ($t = ["0" => PHP_INT_SIZE]) => $t)', 'unknown'],
    'when(), a default that reads another parameter' => ['$this->when($this->title, fn ($a = null, $b = $a) => $b)', 'unknown'],
]);

// A later key must see the outer binding again once a default closure has bound the same name and returned.
it('restores the outer binding after a conditional default binds its parameter', function () {
    $php = '["a" => $this->when($this->title, 1, fn ($local = null) => $local), "b" => $local->email]';

    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe('{ a: number | null; b: string }');
});

// whenLoaded() skips its closure for a null relation, so a morphTo's variadic list holds whichever target loaded.
it('binds a morphTo whenLoaded() variadic parameter to the list of its targets', function (string $resource, string $model, string $type, array $targets) {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Venue::class, Artist::class, Review::class, VenueReview::class, ArtistReview::class]);
    $scope = new AnalysisScope(new ReflectionClass($resource), $model);
    $expr = new AstParser()->parseSource('<?php $this->whenLoaded("reviewable", fn (...$r) => $r);')[0]->expr;

    $result = new ResourceAstAnalyzer(new ReflectionClass($resource), $model, 'toArray', null, $scope)->resolve($expr);

    expect($result['type'])->toBe($type)
        ->and($result['embeddedModelFqcns'] ?? null)->toBe($targets);
})->with([
    'targets from the morph map' => [ReviewResource::class, Review::class, '(Artist | Venue)[]', [Artist::class, Venue::class]],
    'a nullable relation, its null arm dropped' => [ImageResource::class, Image::class, '(User | User)[]', [CrmUser::class, User::class]],
]);

// The value is typed before the claim frees the name it shares, or `$local->profile` would read an unbound `$local`.
it('types transform()\'s value before its claim releases a name the value reads', function () {
    expect(conditionalMethodHandlerResolveOnPost('$this->transform($local->profile, fn ($local) => $local->bio)')['type'])
        ->toBe('string | null');
});

// The callback runs only for a filled value, so a nullable model binds without its null arm.
it('binds transform()\'s callback to a nullable model read without its null arm', function () {
    expect(conditionalMethodHandlerResolveOnPost('$this->resource->author->profile')['type'])->toBe('Profile | null')
        ->and(conditionalMethodHandlerResolveOnPost('$this->transform($this->resource->author->profile, fn ($p) => $p)')['type'])
        ->toBe('Profile');
});

// A parameter holding its default or a list must not reach a receiver chain through a value the call does not pass.
it('keeps an optional or variadic parameter off the property its writer would bind a required one to', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'when(), optional' => ['$this->when($this->author, fn ($a = null) => ["email" => $a?->email])', '{ email: unknown }'],
    'when(), variadic' => ['$this->when($this->author, fn (...$a) => ["email" => $a?->email])', '{ email: unknown }'],
    'whenHas(), variadic' => ['$this->whenHas("author", fn (...$a) => ["email" => $a?->email])', '{ email: unknown }'],
]);

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AggregateAliasPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DriverOverrideModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\WhenNullDroppedArmResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\WhenNullDroppedDefaultResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ConditionableBroadcastEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NullableStringJson;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
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
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\ArtistResource;
use Workbench\App\Http\Resources\ConditionalDefaultsResource;
use Workbench\App\Http\Resources\ConditionalParamEnumResource;
use Workbench\App\Http\Resources\ImageResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\ReviewResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\VenueResource;
use Workbench\App\Http\Resources\WhenHasValueResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Artist;
use Workbench\App\Models\ArtistReview;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Review;
use Workbench\App\Models\User;
use Workbench\App\Models\Venue;
use Workbench\App\Models\VenueReview;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
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
 * Resolve `$this->{$method}($value, $default)`, after a condition for `when()`, with each arm stubbed to a result.
 *
 * @param  array<string, mixed>  $value
 * @param  array<string, mixed>|null  $default  null for a call that passes no default
 * @return array<string, mixed>|null
 */
function conditionalMethodHandlerResolveArms(string $method, array $value, ?array $default = null): ?array
{
    $valueExpr = new Variable('valueArm');
    $defaultExpr = new Variable('defaultArm');
    $arms = [[$valueExpr, $value], ...($default === null ? [] : [[$defaultExpr, $default]])];
    $args = [
        ...($method === 'when' ? [new Arg(new Variable('condition'))] : []),
        ...array_map(static fn (array $arm): Arg => new Arg($arm[0]), $arms),
    ];

    return (new ConditionalMethodHandler)->resolve(
        new MethodCall(new Variable('this'), $method, $args),
        conditionalMethodHandlerScope(),
        new ConditionalMethodHandlerArmStubEngine($arms),
    );
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
        ->and($result)->toBe(['type' => 'string | null', 'optional' => true]);
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

    expect($result)->toBe(['type' => 'number | string | null', 'optional' => false]);
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
            'status_label' => 'string',
            'appended_status_label' => 'string | number',
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

    // json_decode() returns mixed: a plain title decodes to null, so `title`'s own `string` would be a type the key
    // does not always hold. The value stays `unknown` rather than borrowing the attribute's.
    test('an untypable value publishes unknown, not the type of the attribute it names', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(WhenHasValueResource::class), Post::class)->analyze()->properties)->keyBy('name');

        expect($props['title_unresolvable']['type'])->toBe('unknown')
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

// Laravel returns the value whatever it is, so the attribute answers only a value-less call or an EnumResource wrap.
it('never answers an untypable whenHas() or whenAppended() value with the attribute', function (string $php, array $expected) {
    expect(conditionalMethodHandlerResolveOnPost($php))->toMatchArray($expected);
})->with([
    'whenHas(), a closure' => ['$this->whenHas("title", fn ($t) => json_decode($t))', ['type' => 'unknown', 'optional' => true]],
    'whenHas(), a closure and a default' => ['$this->whenHas("title", fn ($t) => json_decode($t), "none")', ['type' => 'unknown', 'optional' => false]],
    'whenHas(), not a closure' => ['$this->whenHas("title", json_decode($this->title))', ['type' => 'unknown', 'optional' => true]],
    'whenAppended(), a closure' => ['$this->whenAppended("title_display", fn () => json_decode($this->title))', ['type' => 'unknown', 'optional' => true]],
    'whenAppended(), a closure Laravel cannot call' => ['$this->whenAppended("title_display", fn ($x) => $x)', ['type' => 'unknown', 'optional' => true]],
    'whenHas(), a match' => [
        '$this->whenHas("status", fn ($s) => match ($s) { \\Workbench\\App\\Enums\\Status::Published => "live", default => "draft" })',
        ['type' => 'string', 'optional' => true],
    ],
]);

// Only whenHas() and whenAppended() publish `unknown` for a closure the engine cannot type: the other three keep the
// flag or the aggregate's type, which the package publishes for their keys.
it('keeps the flag or the aggregate type for an untypable whenExistsLoaded(), whenCounted() or whenAggregated() closure', function (string $php, array $expected) {
    expect(conditionalMethodHandlerResolveOnPost($php))->toMatchArray($expected);
})->with([
    'whenExistsLoaded()' => ['$this->whenExistsLoaded("comments", fn ($e) => json_decode($e))', ['type' => 'boolean', 'optional' => true]],
    'whenExistsLoaded() and a default' => [
        '$this->whenExistsLoaded("comments", fn ($e) => json_decode($e), "none")',
        ['type' => 'boolean | string', 'optional' => false],
    ],
    'whenCounted()' => ['$this->whenCounted("comments", fn ($n) => json_decode($n))', ['type' => 'number', 'optional' => true]],
    'whenAggregated()' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => json_decode($m))', ['type' => 'number | null', 'optional' => true]],
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
    'whenAggregated() closure, ignoring the aggregate' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => "x")', 'string | null'],
    'whenAggregated() closure, returning the aggregate' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => $m)', 'number | null'],
    'whenAggregated() closure, passed the aggregate' => ['$this->whenAggregated("comments", "id", "max", fn ($m) => ["m" => $m])', '{ m: number } | null'],
    'whenAggregated() closure, variadic' => ['$this->whenAggregated("comments", "id", "max", fn (...$m) => $m)', 'number[] | null'],
    'whenAggregated() count closure, passed the count' => ['$this->whenAggregated("comments", "id", "count", fn ($c) => ["c" => $c])', '{ c: number }'],
    'whenLoaded() second parameter, optional int' => ['$this->whenLoaded("author", fn ($a, $b = 5) => $b)', 'number'],
    'transform() callback second parameter, optional int' => ['$this->transform($this->title, fn ($t, $u = 5) => $u)', 'number'],
    'transform() default, variadic, passed the blank value' => ['$this->transform($this->rating, fn ($r) => "x", fn (...$r) => $r)', 'string | (number | null)[]'],
    'transform() default, passed a nullable model' => [
        '$this->transform($this->resource->author->profile, fn ($p) => 1, fn ($p) => $p)',
        'number | Profile | null',
    ],
]);

// The workbench runs SQLite, which returns an integer or decimal aggregate as a number and a date or text MIN()/MAX()
// as a string. Any aggregate but a count is SQL NULL over no rows.
it('publishes whenAggregated()\'s aggregate as the driver returns it', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'max() of a date column' => ['$this->whenAggregated("comments", "created_at", "max")', 'string | null'],
    'max() of a text column' => ['$this->whenAggregated("comments", "content", "max")', 'string | null'],
    'sum(), a null value' => ['$this->whenAggregated("comments", "id", "sum", null)', 'number | null'],
    'sum() of a date column, which proves nothing' => ['$this->whenAggregated("comments", "created_at", "sum")', 'number | null'],
    'max() of a column the related table lacks' => ['$this->whenAggregated("comments", "nope", "max")', 'number | null'],
    'max() over a relation the model lacks' => ['$this->whenAggregated("nope", "id", "max")', 'number | null'],
    'a function the call computes' => ['$this->whenAggregated("comments", "id", $local)', 'number'],
    'count()' => ['$this->whenAggregated("comments", "id", "count")', 'number'],
]);

// The related model's column types the aggregate, so an edit to that model alone must publish the resource again.
it('records the related model an aggregate reads as a dependency', function () {
    DependencyRecorder::start();

    try {
        conditionalMethodHandlerResolveOnPost('$this->whenAggregated("comments", "created_at", "max")');
        $paths = DependencyRecorder::paths();
    } finally {
        DependencyRecorder::stop();
    }

    expect($paths)->toContain((new ReflectionClass(Comment::class))->getFileName());
});

// whenAggregated() names its attribute after the snake-cased relation, so either spelling reaches
// Product::orderItems().
it('reads the relation an aggregate names, spelled either way', function (string $relation) {
    $expr = new AstParser()->parseSource('<?php $this->whenAggregated("'.$relation.'", "created_at", "max");')[0]->expr;
    $scope = new AnalysisScope(new ReflectionClass(ProductResource::class), Product::class);

    expect(new ResourceAstAnalyzer(new ReflectionClass(ProductResource::class), Product::class, 'toArray', null, $scope)
        ->resolve($expr)['type'])->toBe('string | null');
})->with(['the method name' => 'orderItems', 'snake-cased' => 'order_items']);

// Laravel reads the aggregate through the model's own accessor or cast, so the model's declaration wins over the
// driver: an accessor can turn the SQL NULL into a value, while a built-in cast passes it through.
it('reads a column aggregate through the model\'s own accessor, cast or @property', function (string $php, string $type, string $driver = 'sqlite') {
    app()->instance(ModelAttributeResolver::class, new DriverOverrideModelAttributeResolver($driver));
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), AggregateAliasPost::class);

    expect(new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), AggregateAliasPost::class, 'toArray', null, $scope)
        ->resolve($expr)['type'])->toBe($type);
})->with([
    'a string cast' => ['$this->whenAggregated("comments", "post_id", "sum")', 'string | null'],
    'a datetime cast, on a driver the rule has no evidence for' => ['$this->whenAggregated("comments", "created_at", "max")', 'string | null', 'oracle'],
    'a datetime cast with a format, on a driver the rule has no evidence for' => ['$this->whenAggregated("comments", "created_at", "min")', 'string | null', 'oracle'],
    'an @property tag, where MySQL\'s own AVG() is a string' => ['$this->whenAggregated("comments", "post_id", "avg")', 'number | null', 'mysql'],
    'an accessor that coalesces the null' => ['$this->whenAggregated("comments", "post_id", "max")', 'number'],
    'an accessor, passed to a closure' => ['$this->whenAggregated("comments", "post_id", "max", fn ($m) => ["m" => $m])', '{ m: number }'],
    'a string cast, passed to a closure' => ['$this->whenAggregated("comments", "post_id", "sum", fn ($s) => ["s" => $s])', '{ s: string } | null'],
    'no declaration, the driver\'s number' => ['$this->whenAggregated("comments", "post_id", "min")', 'number | null'],
]);

// A parameter the call passes nothing holds its default, which PHP evaluates as a constant expression: a list literal
// is a list, never the record the engine types an array literal as, and a list the evaluator cannot read binds nothing.
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
    'when(), a list the evaluator cannot read' => ['$this->when($this->title, fn ($t = [new Foo]) => $t)', 'unknown'],
    'when(), a record the evaluator cannot read' => ['$this->when($this->title, fn ($t = ["a" => new Foo]) => $t)', '{ a: unknown }'],
    'when(), a record holding a list the evaluator cannot read' => ['$this->when($this->title, fn ($t = ["a" => [new Foo]]) => $t)', 'unknown'],
    'when(), an int-like key the evaluator cannot read' => ['$this->when($this->title, fn ($t = ["0" => new Foo]) => $t)', 'unknown'],
    'when(), a numeric key the evaluator cannot read' => ['$this->when($this->title, fn ($t = ["1.5" => new Foo]) => $t)', 'unknown'],
    'when(), a default that reads another parameter' => ['$this->when($this->title, fn ($a = null, $b = $a) => $b)', 'unknown'],
    'when(), a list whose evaluation errors' => ['$this->when($this->title, fn ($t = [1 / 0]) => $t)', 'unknown'],
    'when(), a closure, which json_encode() writes as {}' => ['$this->when($this->title, fn ($t = static function () { return 1; }) => $t)', 'unknown'],
    'when(), a first-class callable' => ['$this->when($this->title, fn ($t = strlen(...)) => $t)', 'unknown'],
    'when(), a cast' => ['$this->when($this->title, fn ($t = (int) "5") => $t)', 'number'],
]);

// A resource's removeMissingValues() re-indexes an array whose keys all pass is_numeric() into a list, at any depth, so
// the record those keys would type as is not what reaches JSON.
it('binds nothing for a default a resource re-indexes into a list', function (string $default) {
    expect(conditionalMethodHandlerResolveOnPost('$this->when($this->title, fn ($t = '.$default.') => $t)')['type'])
        ->toBe('unknown');
})->with([
    'a float-string key' => ['["1.5" => "x"]'],
    'an exponent key' => ['["1e3" => "x"]'],
    'a key with a leading space' => ['[" 1" => "x"]'],
    'a negative-zero key' => ['["-0" => "x"]'],
    'an int key beside a float-string one' => ['[1 => "a", "1.5" => "b"]'],
    'a float-string key nested in a record' => ['["a" => ["2.5" => 1]]'],
    'a float-string key over a global constant' => ['["1.5" => PHP_INT_SIZE]'],
]);

// Each of these is a constant expression whose value serializes to a string, so its parameter binds as one.
it('binds a default of a global or magic constant, an enum case property or a Carbon date to its type', function (string $default, string $type) {
    expect(conditionalMethodHandlerResolveOnPost('$this->when($this->title, fn ($t = '.$default.') => $t)')['type'])
        ->toBe($type);
})->with([
    'an enum case ->name' => ['\\Workbench\\App\\Enums\\Status::Published->name', 'string'],
    'an enum case ->value' => ['\\Workbench\\App\\Enums\\Status::Published->value', 'number'],
    '__CLASS__' => ['__CLASS__', 'string'],
    '__FUNCTION__' => ['__FUNCTION__', 'string'],
    '__METHOD__' => ['__METHOD__', 'string'],
    '__DIR__' => ['__DIR__', 'string'],
    '__FILE__' => ['__FILE__', 'string'],
    '__NAMESPACE__' => ['__NAMESPACE__', 'string'],
    '__TRAIT__' => ['__TRAIT__', 'string'],
    '__PROPERTY__' => ['__PROPERTY__', 'string'],
    '__LINE__' => ['__LINE__', 'number'],
    'PHP_EOL' => ['PHP_EOL', 'string'],
    'a global constant in a list' => ['[PHP_INT_SIZE]', 'number[]'],
    'a Carbon date' => ['new \\Illuminate\\Support\\Carbon("2020-01-01 00:00:00")', 'string'],
    'a DateTime, which json_encode() writes as an object' => ['new \\DateTime("2020-01-01")', 'unknown'],
    'a ?string serialization returning null' => ['new \\'.NullableStringJson::class.'()', 'string | null'],
    'a ?string serialization returning a string' => ['new \\'.NullableStringJson::class.'("x")', 'string | null'],
    '__TRAIT__ as a key, whose value depends on where it appears' => ['[__TRAIT__ => 1]', 'unknown'],
    '__FILE__ as a key' => ['[__FILE__ => 1]', 'unknown'],
    'a comparison on __TRAIT__, which never folds' => ['__TRAIT__ === "" ? 1 : "a"', 'number | string'],
]);

// A later key must see the outer binding again once a default closure has bound the same name and returned.
it('restores the outer binding after a conditional default binds its parameter', function () {
    $php = '["a" => $this->when($this->title, 1, fn ($local = null) => $local), "b" => $local->email]';

    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe('{ a: number | null; b: string }');
});

// timestamps_as_date publishes a Carbon attribute as Date, but the value a default holds reaches JSON as an ISO string.
it('binds a Carbon new default as string under timestamps_as_date', function (string $class) {
    config()->set('ts-publish.timestamps_as_date', true);

    expect(conditionalMethodHandlerResolveOnPost('$this->when($this->title, fn ($t = new '.$class.'("2020-01-01")) => $t)')['type'])
        ->toBe('string');
})->with([
    'Illuminate\\Support\\Carbon' => ['\\Illuminate\\Support\\Carbon'],
    'Carbon\\Carbon' => ['\\Carbon\\Carbon'],
    'Carbon\\CarbonImmutable' => ['\\Carbon\\CarbonImmutable'],
]);

// whenLoaded() returns null, before it reads the value, for a relation loaded as null; a to-many loads a collection.
it('adds the null a relation loaded as null returns to every whenLoaded() value arm', function (string $php, string $type) {
    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'a closure over a nullable to-one' => ['$this->whenLoaded("categoryRel", fn ($c) => $c->name)', 'string | null'],
    'a closure whose nullsafe chain already yields null' => ['$this->whenLoaded("categoryRel", fn () => $this->categoryRel?->name)', 'string | null'],
    'a value that is not a closure' => ['$this->whenLoaded("categoryRel", "loaded")', 'string | null'],
    'a first-class callable' => ['$this->whenLoaded("categoryRel", \\Workbench\\App\\Http\\Resources\\CategoryResource::make(...))', 'CategoryResource | null'],
    'a closure and a default' => ['$this->whenLoaded("categoryRel", fn ($c) => $c->name, "absent")', 'string | null'],
    'a literal null value, which Laravel swaps for the identity closure' => ['$this->whenLoaded("categoryRel", null, "absent")', 'Category | string | null'],
    'a variadic list, which never holds null' => ['$this->whenLoaded("categoryRel", fn (...$c) => $c)', 'Category[] | null'],
    'a closure over a relation the model does not declare' => ['$this->whenLoaded("featured", fn ($f) => "x")', 'string | null'],
    'a closure over a non-nullable to-one' => ['$this->whenLoaded("author", fn ($a) => $a->name)', 'string'],
    'a closure over a to-many' => ['$this->whenLoaded("comments", fn ($c) => $c->pluck("id"))', 'number[]'],
    'a closure the engine cannot type' => ['$this->whenLoaded("categoryRel", fn ($c) => json_decode($c->name))', 'unknown'],
]);

// Laravel swaps a literal null value for the identity closure, so the key reads the relation; one that does not type
// would widen it to `unknown`, so the null value keeps its own type there, as it did before the relation was read.
it('reads a literal null whenLoaded() value as the relation only when the relation types', function (string $php, string $type, bool $optional) {
    $result = conditionalMethodHandlerResolveOnPost($php);

    expect($result['type'])->toBe($type)
        ->and($result['optional'])->toBe($optional);
})->with([
    'a relation the model does not declare, with a default' => ['$this->whenLoaded("featured", null, "x")', 'string | null', false],
    'a relation the model does not declare' => ['$this->whenLoaded("featured", null)', 'null', true],
    'a relation name the engine cannot read, with a default' => ['$this->whenLoaded($rel, null, "x")', 'string | null', false],
    'a relation name the engine cannot read' => ['$this->whenLoaded($rel, null)', 'null', true],
    'a declared relation' => ['$this->whenLoaded("categoryRel", null)', 'Category | null', true],
]);

it('adds no whenLoaded() null arm while nullable_relations is off', function (string $php, string $type) {
    config()->set('ts-publish.models.nullable_relations', false);

    expect(conditionalMethodHandlerResolveOnPost($php)['type'])->toBe($type);
})->with([
    'a declared to-one' => ['$this->whenLoaded("categoryRel", fn ($c) => $c->name)', 'string'],
    'an undeclared relation' => ['$this->whenLoaded("featured", fn ($f) => "x")', 'string'],
]);

// With no backing model every relation reads as undeclared, so nullable_relations alone decides the arm.
it('adds the whenLoaded() null arm on a subject with no backing model only while nullable_relations is on', function (bool $nullableRelations, string $type) {
    config()->set('ts-publish.models.nullable_relations', $nullableRelations);
    $valueExpr = new String_('loaded');
    $expr = new MethodCall(new Variable('this'), 'whenLoaded', [new Arg(new String_('author')), new Arg($valueExpr)]);
    $engine = new ConditionalMethodHandlerArmStubEngine([[$valueExpr, ['type' => 'string', 'optional' => false]]]);

    expect((new ConditionalMethodHandler)->resolve($expr, conditionalMethodHandlerScope(), $engine))
        ->toBe(['type' => $type, 'optional' => true]);
})->with([
    'nullable_relations on' => [true, 'string | null'],
    'nullable_relations off' => [false, 'string'],
]);

// whenLoaded() calls the closure only for a loaded value that is not null, so the list never holds null; the key takes
// the null it returns instead, for a relation that can load as null.
it('binds a morphTo whenLoaded() variadic parameter to the list of its targets', function (string $resource, string $model, string $type, array $targets) {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Venue::class, Artist::class, Review::class, VenueReview::class, ArtistReview::class]);
    $scope = new AnalysisScope(new ReflectionClass($resource), $model);
    $expr = new AstParser()->parseSource('<?php $this->whenLoaded("reviewable", fn (...$r) => $r);')[0]->expr;

    $result = new ResourceAstAnalyzer(new ReflectionClass($resource), $model, 'toArray', null, $scope)->resolve($expr);

    expect($result['type'])->toBe($type)
        ->and($result['embeddedModelFqcns'] ?? null)->toBe($targets);
})->with([
    'targets from the morph map' => [ReviewResource::class, Review::class, '(Artist | Venue)[]', [Artist::class, Venue::class]],
    'a nullable relation, its null arm on the key' => [ImageResource::class, Image::class, '(User | User)[] | null', [CrmUser::class, User::class]],
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

// A value and a default that spell one name for two classes cannot be told apart by their text, so each keeps a member.
describe('an explicit default that spells its value\'s name for another class', function () {
    it('keeps a member and a queue entry for each class, the value\'s first', function (array $value, array $default, array $union) {
        expect(conditionalMethodHandlerResolveArms('when', $value, $default))->toBe($union);
    })->with([
        'two models' => [
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class],
            ['type' => 'User | User | null', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class, User::class]],
        ],
        'two resources' => [
            ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class],
            ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => CrmUserResource::class],
            [
                'type' => 'UserResource | UserResource',
                'optional' => false,
                'embeddedResourceFqcns' => [UserResource::class, CrmUserResource::class],
            ],
        ],
    ]);

    it('still leaves `[]` out beside a real array, whichever arm holds it', function (array $value, array $default) {
        expect(conditionalMethodHandlerResolveArms('when', $value, $default))->toBe([
            'type' => 'User[] | User[]',
            'optional' => false,
            'embeddedModelFqcns' => [User::class, CrmUser::class],
        ]);
    })->with([
        'the value' => [
            ['type' => 'User[] | never[]', 'optional' => false, 'embeddedModelFqcns' => [User::class]],
            ['type' => 'User[]', 'optional' => false, 'modelFqcn' => CrmUser::class],
        ],
        'the default' => [
            ['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class],
            ['type' => 'never[] | User[]', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]],
        ],
        'a default that is nothing else, beside a value that spells both classes' => [
            ['type' => 'User[] | User[]', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
            ['type' => 'never[]', 'optional' => false],
        ],
    ]);
});

// whenNull() returns its value only when that value is null, so nothing the value names reaches the key.
describe('whenNull()', function () {
    it('names the default\'s class alone, whatever model its value is', function (array $default, array $result) {
        $value = ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class];

        expect(conditionalMethodHandlerResolveArms('whenNull', $value, $default))->toBe($result);
    })->with([
        'a default of the same name' => [
            ['type' => 'User | null', 'optional' => false, 'modelFqcn' => CrmUser::class],
            ['type' => 'User | null', 'optional' => false, 'embeddedModelFqcns' => [CrmUser::class]],
        ],
        'a default of another name' => [
            ['type' => 'Post', 'optional' => false, 'modelFqcn' => Post::class],
            ['type' => 'Post | null', 'optional' => false, 'embeddedModelFqcns' => [Post::class]],
        ],
    ]);

    it('carries no class, enum or import its value names', function (array $value) {
        expect(conditionalMethodHandlerResolveArms('whenNull', $value))->toBe(['type' => 'null', 'optional' => true]);
    })->with([
        'a model' => [['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class]],
        'two models that share a name' => [
            ['type' => 'User | User | null', 'optional' => false, 'embeddedModelFqcns' => [User::class, CrmUser::class]],
        ],
        'a resource' => [['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class]],
        'an enum' => [['type' => 'StatusType | null', 'optional' => false, 'directEnumFqcn' => Status::class]],
        'an imported type' => [['type' => 'Money | null', 'optional' => false, 'customImports' => ['@/types/money' => ['Money']]]],
    ]);

    // The value is still resolved, so the arm it drops is counted and the local's `@var` type answers for its null.
    it('counts an arm dropped inside its value, so an annotated local publishes its declared type', function () {
        config()->set('ts-publish.output_to_files', false);

        $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer(WhenNullDroppedArmResource::class));

        expect(array_values(preg_grep('/^\s*label\b/', explode("\n", $content)) ?: []))->toBe(['    label?: string | null;']);
    });

    // A default the engine cannot type is counted as a dropped arm, so the local's `@var` type answers for its null.
    it('counts a default it leaves out, so an annotated local publishes its declared type', function () {
        config()->set('ts-publish.output_to_files', false);

        $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer(WhenNullDroppedDefaultResource::class));

        expect(array_values(preg_grep('/^\s*label\b/', explode("\n", $content)) ?: []))->toBe(['    label: string | null;']);
    });
});

// A default the engine cannot type is left out of the union, and recorded only beside a value arm with a type.
it('records a default it leaves out beside a typed value arm, and nothing beside an unknown one', function (string $php, array $sites) {
    DroppedUnionArms::start();

    try {
        conditionalMethodHandlerResolveOnPost($php);
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    expect(array_map(fn (array $arm): string => $arm['expression'].' at '.$arm['site'], $dropped))->toBe($sites);
})->with([
    'when()' => ['$this->when($this->id > 0, $this->title, $this->undefined_column)', ['$this->undefined_column at conditional-default']],
    'whenNull()' => ['$this->whenNull($this->title, $this->undefined_column)', ['$this->undefined_column at conditional-default']],
    'a typed default' => ['$this->when($this->id > 0, $this->title, 0)', []],
    'an unknown value arm' => ['$this->whenPivotLoaded("team_user", null, $this->undefined_column)', []],
    'a default that needs an argument' => ['$this->when($this->id > 0, $this->title, fn ($x) => $this->undefined_column)', []],
]);

// `[]` is assignable to an array type and to nothing else, so it leaves the union only beside an array, in either arm.
it('drops an empty-array arm only where the other arm holds an array', function (string $value, string $default, string $type) {
    $result = conditionalMethodHandlerResolveArms(
        'when',
        ['type' => $value, 'optional' => false],
        ['type' => $default, 'optional' => false],
    );

    expect($result)->toBe(['type' => $type, 'optional' => false]);
})->with([
    'an empty value beside an array default' => ['never[]', 'string[]', 'string[]'],
    'an empty default beside an array value' => ['string[]', 'never[]', 'string[]'],
    'an empty value beside a default that is no array' => ['never[]', 'string', 'never[] | string'],
]);

// Where no two classes share a name, the union by class is the union by text.
it('unions a value and a default of two different names as a merge by text does', function (string $method, string $type) {
    $value = ['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class];
    $default = ['type' => 'Post', 'optional' => false, 'modelFqcn' => Post::class];

    expect(conditionalMethodHandlerResolveArms($method, $value, $default))->toBe([
        'type' => $type,
        'optional' => false,
        'embeddedModelFqcns' => [User::class, Post::class],
    ]);
})->with([
    'when()' => ['when', 'User | Post | null'],
    'whenNotNull(), which keeps its value\'s class and strips its null' => ['whenNotNull', 'User | Post'],
]);

// A default's union counts its members, so a `null` beside two enum resources keeps it from being read as a union of
// enum resources: the enums are embedded, and publish as their own types.
it('does not read two enum resources and a null as a union of enum resources', function () {
    $value = ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class];
    $default = ['type' => 'VisibilityType | null', 'optional' => false, 'enumFqcn' => Visibility::class];

    expect(conditionalMethodHandlerResolveArms('when', $value, $default))->toBe([
        'type' => 'StatusType | VisibilityType | null',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class, Visibility::class],
    ]);
});

// when() calls its closure with no argument, so one requiring a parameter throws whenever the condition holds. The
// parameter stays bound, so the key publishes what the intended `fn () => $this->status` would.
it('warns that a when() or unless() closure requiring a parameter throws, and keeps its binding', function () {
    $analysis = new ResourceAstAnalyzer(new ReflectionClass(ConditionalParamEnumResource::class), Order::class)->analyze();
    $statusBare = collect($analysis->properties)->firstWhere('name', 'status_bare');

    // The three when() closures warn; the whenLoaded() closure beside them is passed its relation.
    expect($statusBare['type'])->toBe('OrderStatusType')
        ->and(AnalysisWarnings::all())->toHaveCount(3)
        ->and(AnalysisWarnings::all())->toContain([
            'subject' => ConditionalParamEnumResource::class,
            'message' => 'when() on line 41 calls its closure with no arguments, so the closure throws ArgumentCountError whenever it runs. Read the value inside the closure instead of taking it as a parameter.',
        ]);
});

it('records one warning, naming the call, for a when() or unless() closure that requires a parameter', function (string $php, string $message) {
    conditionalMethodHandlerResolveOnPost($php);

    expect(AnalysisWarnings::all())->toBe([['subject' => PostResource::class, 'message' => $message]]);
})->with([
    'when()' => [
        '$this->when($this->title, fn ($t) => $t)',
        'when() on line 1 calls its closure with no arguments, so the closure throws ArgumentCountError whenever it runs. Read the value inside the closure instead of taking it as a parameter.',
    ],
    'unless()' => [
        '$this->unless($this->title, fn ($t) => $t)',
        'unless() on line 1 calls its closure with no arguments, so the closure throws ArgumentCountError whenever it runs. Read the value inside the closure instead of taking it as a parameter.',
    ],
    'a condition that binds nothing' => [
        '$this->when($this->id > 0, fn ($t) => $t)',
        'when() on line 1 calls its closure with no arguments, so the closure throws ArgumentCountError whenever it runs. Read the value inside the closure instead of taking it as a parameter.',
    ],
]);

it('records the warning once, however often the call is analyzed', function () {
    conditionalMethodHandlerResolveOnPost('$this->when($this->title, fn ($t) => $t)');
    conditionalMethodHandlerResolveOnPost('$this->when($this->title, fn ($t) => $t)');

    expect(AnalysisWarnings::all())->toHaveCount(1);
});

// A closure Laravel can call with no argument never throws for it, so nothing is warned of.
it('records no warning for a when() or unless() value Laravel can call with no arguments', function (string $php) {
    conditionalMethodHandlerResolveOnPost($php);

    expect(AnalysisWarnings::all())->toBe([]);
})->with([
    'an optional parameter' => ['$this->when($this->title, fn ($t = null) => $t)'],
    'a variadic parameter' => ['$this->when($this->title, fn (...$t) => $t)'],
    'no parameter' => ['$this->when($this->title, fn () => $this->title)'],
    'a value that is no closure' => ['$this->when($this->title, $this->title)'],
    'unless(), an optional parameter' => ['$this->unless($this->title, fn ($t = null) => $t)'],
]);

// Conditionable::when() passes its callback the object and the value, so a callback that takes them is right. Only an
// API resource's profile reads `$this->when()` as JsonResource::when(), which passes nothing.
it('records no warning for a $this->when() whose subject is no API resource', function () {
    $subject = new ReflectionClass(ConditionableBroadcastEvent::class);
    $call = new AstParser()->parseSource('<?php $this->when($this->user->exists, fn ($event, $value) => $value);')[0]->expr;

    new ResourceAstAnalyzer($subject, User::class, 'broadcastWith', null, new AnalysisScope($subject, User::class))->resolve($call);

    expect(AnalysisWarnings::all())->toBe([]);
});

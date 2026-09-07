<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
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
use Workbench\App\Http\Resources\ConditionalDefaultsResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\User;

/**
 * An AnalysisScope for tests that don't need a real backing model.
 */
function conditionalMethodHandlerScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ConditionalDefaultsResource::class));
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

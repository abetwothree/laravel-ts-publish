<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArm;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArms;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionTruth;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValue;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Variants\Fixtures\ConditionalArmsResource;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;

/**
 * The expression a fixture method returns.
 */
function conditionalArmsReturned(string $method): Expr
{
    $return = resolve(MethodLocator::class)->locate(ConditionalArmsResource::class, $method)?->method->stmts[0] ?? null;
    assert($return instanceof Return_ && $return->expr !== null);

    return $return->expr;
}

/**
 * The arms the dispatcher of an analyzer over a model hands over for an expression, once that analyzer typed it.
 *
 * @param  class-string<Model>|null  $model
 */
function conditionalArmsOf(Expr $expr, ?string $model = Post::class, Reading $reading = Reading::Encoded): ?ConditionalArms
{
    $analyzer = new class(new ReflectionClass(ConditionalArmsResource::class), $model, reading: $reading) extends ResourceAstAnalyzer
    {
        /** Type an expression, then read its arms from the handler that typed it. */
        public function armsOf(Expr $expr): ?ConditionalArms
        {
            $this->resolve($expr);

            return $this->dispatcher()->conditionalArms($expr, $this->scope, $this);
        }
    };

    return $analyzer->armsOf($expr);
}

/**
 * The arms a fixture method's returned expression hands over.
 *
 * @param  class-string<Model>|null  $model
 */
function conditionalArmsOfMethod(string $method, ?string $model = Post::class, Reading $reading = Reading::Encoded): ConditionalArms
{
    $arms = conditionalArmsOf(conditionalArmsReturned($method), $model, $reading);
    assert($arms !== null);

    return $arms;
}

it('hands over a ternary\'s arms: the condition guarding its true arm, and nothing guarding the other', function () {
    $ternary = conditionalArmsReturned('ternary');
    assert($ternary instanceof Ternary);
    $arms = conditionalArmsOf($ternary)?->arms ?? [];

    expect($arms)->toHaveCount(2)
        ->and($arms[0]->guard)->toBe($ternary->cond)
        ->and($arms[0]->body)->toBe($ternary->if)
        ->and($arms[0]->result['type'] ?? null)->toBe('string')
        ->and($arms[1]->guard)->toBeNull()
        ->and($arms[1]->body)->toBe($ternary->else)
        ->and($arms[1]->result['type'] ?? null)->toBe('null')
        ->and($arms[0]->throws || $arms[1]->throws)->toBeFalse();
});

it('types the arm an instanceof condition proves as the narrowed read does', function () {
    $ternary = conditionalArmsReturned('narrowedTernary');
    assert($ternary instanceof Ternary && $ternary->if !== null);
    $arms = conditionalArmsOf($ternary, model: null)?->arms ?? [];

    expect($arms[0]->result['type'] ?? null)->toBe('string')
        ->and(new ResourceAstAnalyzer(new ReflectionClass(ConditionalArmsResource::class))->resolve($ternary->if)['type'])->toBe('unknown');
});

it('hands over no arms for a short ternary, whose condition is its own true arm', function () {
    expect(conditionalArmsOf(conditionalArmsReturned('shortTernary')))->toBeNull();
});

it('hands over one arm per match arm, guarded by the subject being any of its conditions', function () {
    $match = conditionalArmsReturned('matchCases');
    assert($match instanceof Match_);
    $arms = conditionalArmsOf($match)?->arms ?? [];
    $read = ReadPath::of($match->cond);
    assert($read !== null && $arms[0]->guard !== null);
    $scope = new AnalysisScope(new ReflectionClass(ConditionalArmsResource::class), Post::class);
    $decides = fn (Priority $case): ?bool => ConditionTruth::decide($arms[0]->guard, $read, new DiscriminantValue($case, (string) $case->value), $scope);

    expect($arms)->toHaveCount(2)
        ->and($decides(Priority::Medium))->toBeTrue()
        ->and($decides(Priority::Low))->toBeTrue()
        ->and($decides(Priority::High))->toBeFalse()
        ->and($arms[0]->result['type'] ?? null)->toBe('string')
        ->and($arms[1]->guard)->toBeNull()
        ->and($arms[1]->result['type'] ?? null)->toBe('number');
});

it('hands over a match arm that throws, and a last arm that throws for a match with no default', function (string $method, int $count) {
    $arms = conditionalArmsOfMethod($method)->arms;
    $last = $arms[count($arms) - 1];

    expect($arms)->toHaveCount($count)
        ->and($last->guard)->toBeNull()
        ->and($last->result)->toBeNull()
        ->and($last->throws)->toBeTrue()
        ->and($arms[0]->throws)->toBeFalse();
})->with([
    'a throwing default' => ['matchThrows', 2],
    'no default' => ['matchWithoutDefault', 3],
]);

// PHP tries `default` only once no other arm matches, wherever it is written.
it('hands over a match\'s default last, wherever it is written', function () {
    $arms = conditionalArmsOfMethod('matchDefaultFirst')->arms;

    expect($arms)->toHaveCount(2)
        ->and($arms[0]->guard)->not->toBeNull()
        ->and($arms[0]->result['type'] ?? null)->toBe('string')
        ->and($arms[1]->guard)->toBeNull()
        ->and($arms[1]->result['type'] ?? null)->toBe('number');
});

it('hands over a when()\'s value guarded by its condition, and the removal a missing default leaves', function () {
    $call = conditionalArmsReturned('whenWithoutDefault');
    assert($call instanceof MethodCall);
    $arms = conditionalArmsOf($call)?->arms ?? [];

    expect($arms)->toHaveCount(2)
        ->and($arms[0]->guard)->toBe($call->getArgs()[0]->value)
        ->and($arms[0]->result['type'] ?? null)->toBe('string | null')
        ->and($arms[1]->guard)->toBeNull()
        ->and($arms[1]->body)->toBeNull()
        ->and($arms[1]->result)->toBeNull()
        ->and($arms[1]->throws)->toBeFalse();
});

it('hands over the [] a missing default sends where nothing filters the key', function () {
    $arms = conditionalArmsOfMethod('whenWithoutDefault', reading: Reading::EncodedUnfiltered)->arms;

    expect($arms[1]->result)->toBe(['type' => 'never[]', 'optional' => false]);
});

it('negates an unless()\'s condition, and hands over its default', function () {
    $call = conditionalArmsReturned('unlessWithDefault');
    assert($call instanceof MethodCall);
    $arms = conditionalArmsOf($call)?->arms ?? [];

    expect($arms[0]->guard)->toBeInstanceOf(BooleanNot::class)
        ->and($arms[0]->guard instanceof BooleanNot ? $arms[0]->guard->expr : null)->toBe($call->getArgs()[0]->value)
        ->and($arms[0]->result['type'] ?? null)->toBe('string')
        ->and($arms[1]->body)->toBe($call->getArgs()[2]->value)
        ->and($arms[1]->result['type'] ?? null)->toBe('string');
});

it('hands over a default closure Laravel cannot call as an arm that throws', function () {
    $arms = conditionalArmsOfMethod('uncallableDefault')->arms;

    expect($arms[1]->result)->toBeNull()
        ->and($arms[1]->throws)->toBeTrue()
        ->and($arms[0]->result)->toBe(['type' => 'number', 'optional' => false]);
});

it('keeps a when() value Laravel may remove optional in its own arm', function () {
    $arms = conditionalArmsOfMethod('removableValue')->arms;

    expect($arms[0]->result['optional'] ?? null)->toBeTrue();
});

it('selects the first arm whose guard holds or is absent, and nothing past an undecided guard', function (array $decisions, ?int $selected) {
    $arms = new ConditionalArms([
        new ConditionalArm(new Variable('a'), null, ['type' => 'string', 'optional' => false]),
        new ConditionalArm(new Variable('b'), null, ['type' => 'number', 'optional' => false]),
        new ConditionalArm(null, null, null),
    ]);
    $decide = fn (Expr $guard): ?bool => $guard instanceof Variable && is_string($guard->name) ? $decisions[$guard->name] : null;

    expect($arms->select($decide))->toBe($selected === null ? null : $arms->arms[$selected]);
})->with([
    'the first holds' => [['a' => true, 'b' => null], 0],
    'the second holds' => [['a' => false, 'b' => true], 1],
    'neither holds' => [['a' => false, 'b' => false], 2],
    'the first is undecided' => [['a' => null, 'b' => true], null],
    'the second is undecided' => [['a' => false, 'b' => null], null],
]);

it('selects nothing where every guard fails and no arm is unguarded', function () {
    $arms = new ConditionalArms([new ConditionalArm(new Variable('a'), null, null)]);

    expect($arms->select(fn (Expr $guard): bool => false))->toBeNull();
});

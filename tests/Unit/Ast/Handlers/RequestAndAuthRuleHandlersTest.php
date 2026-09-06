<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\StarterKit\StarterKitMiddleware;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Requests\DynamicRequest;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** An engine no rule in this file may call: every rule here reads the receiver, never a sub-expression. */
function requestRuleEngine(): ExpressionEngine
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

function requestRuleScope(bool $seeded = true): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));

    if ($seeded) {
        $scope->requestVarNames = ['request' => Request::class];
    }

    return $scope;
}

/** A scope seeded like `InertiaFormRequestController::store(StorePostRequest $request)`. */
function formRequestScope(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $scope->requestVarNames = ['request' => StorePostRequest::class];

    return $scope;
}

function requestCall(string $method): MethodCall
{
    return new MethodCall(new Variable('request'), $method, [new Arg(new String_('key'))]);
}

it('types a Request method call from the reflected signature', function (string $method, string $type) {
    expect((new KnownMethodRuleHandler)->resolve(requestCall($method), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => $type, 'optional' => false]);
})->with([
    // Every name the superseded hardcoded match knew except `cookie`, reproduced by reflection.
    ['url', 'string'],
    ['fullUrl', 'string'],
    ['path', 'string'],
    ['string', 'string'],
    ['integer', 'number'],
    ['boolean', 'boolean'],
    ['hasCookie', 'boolean'],
    // Names the match never listed, now typed because the signature carries them.
    ['method', 'string'],
    ['decodedPath', 'string'],
    ['schemeAndHttpHost', 'string'],
    ['ip', 'string | null'],
    ['bearerToken', 'string | null'],
    ['ajax', 'boolean'],
    ['expectsJson', 'boolean'],
    ['routeIs', 'boolean'],
    ['filled', 'boolean'],
    // An element type the docblock states outright, unlike the bare `@return array` of `all()`.
    ['getLanguages', 'string[]'],
    // A class json_encode really does render as a string, unlike `interval()`'s CarbonInterval.
    ['date', 'string | null'],
]);

it('types $request->user() through the auth provider model', function () {
    expect((new KnownMethodRuleHandler)->resolve(requestCall('user'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class]);
});

it('answers $request->user() before the reflected-type acceptor gets a turn', function () {
    app()->instance(ReflectedTypeAcceptor::class, new class
    {
        /** @param array<string, mixed> $tsInfo */
        public function accept(array $tsInfo): never
        {
            throw new RuntimeException('user() must come from the auth model, not from reflection');
        }
    });

    expect((new KnownMethodRuleHandler)->resolve(requestCall('user'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class]);
});

it('declines the whole rule table when the receiver is not a known Request variable', function () {
    expect((new KnownMethodRuleHandler)->resolve(requestCall('url'), requestRuleScope(seeded: false), requestRuleEngine()))
        ->toBeNull()
        ->and((new KnownMethodRuleHandler)->resolve(requestCall('user'), requestRuleScope(seeded: false), requestRuleEngine()))
        ->toBeNull();
});

it('declines a Request method whose reflected type is unusable', function (string $method) {
    expect((new KnownMethodRuleHandler)->resolve(requestCall($method), requestRuleScope(), requestRuleEngine()))
        ->toBeNull();
})->with([
    'mixed @return' => ['session'],
    'no reflectable declaration — a Macroable __call' => ['validate'],
    'void' => ['setLaravelSession'],
    'never' => ['dd'],
    // A page prop is JSON: reflection cannot tell a bare `@return array` list from a string-keyed map,
    // and `unknown[]` reads as a list. `only(['search'])` encodes as {"search":"abc"}, not an array.
    'bare @return array, encoded as a map' => ['only'],
    'bare @return array behind a union arm' => ['cookie'],
    'a union arm reflection could not type' => ['getContent'],
    // json_encode ignores __toString: an UploadedFile or a CarbonInterval arrives as an object.
    '@return class that encodes as an object' => ['interval'],
    'a docblock class token laundered to string' => ['allFiles'],
    'a signature class token laundered to string' => ['image'],
]);

// `getUserResolver(): Closure` is the live case for the token gate: emitting `Closure` would compile
// to a TS2304, since no import can be generated for it. The acceptor rejects any non-Model class.
it('declines a Request method returning a class token it cannot import', function () {
    expect((new KnownMethodRuleHandler)->resolve(requestCall('getUserResolver'), requestRuleScope(), requestRuleEngine()))
        ->toBeNull();
});

it('scans every arm of a union return type for a class that does not serialize', function () {
    $subject = new class
    {
        public function upload(): UploadedFile|string
        {
            return 'x';
        }

        public function stamp(): Carbon|string
        {
            return 'x';
        }

        public function combo(): UploadedFile&Countable
        {
            return 'x';
        }

        // DNF: an intersection arm nested inside a union — the case a flat instanceof check drops.
        public function dnf(): (UploadedFile&Countable)|string
        {
            return 'x';
        }
    };

    $check = fn (string $method): bool => (fn () => $this->serializesAsReflected(new ReflectionMethod($subject, $method)))
        ->call(new KnownMethodRuleHandler);

    expect($check('upload'))->toBeFalse()   // UploadedFile is not JsonSerializable
        ->and($check('stamp'))->toBeTrue()  // Carbon is
        ->and($check('combo'))->toBeFalse() // intersection arm: UploadedFile is not JsonSerializable
        ->and($check('dnf'))->toBeFalse();  // DNF arm: the intersection nested in the union
});

// The decline above is a fall-through, not a result: requestMethodRule() runs before knownMethodRule()
// in the same handler, so answering 'unknown' rather than null would swallow the rules below.
it('leaves knownMethodRule its turn on a Request receiver reflection cannot type', function () {
    expect((new KnownMethodRuleHandler)->resolve(requestCall('can'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'boolean', 'optional' => false]);
});

// The same constraint through the whole dispatcher rather than the handler alone. It is worth the
// duplication: the assertion above is otherwise the only test that fails when the rule stops declining.
it('leaves knownMethodRule its turn through the dispatcher too', function () {
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(StarterKitMiddleware::class), null, 'share');

    expect($analyzer->resolve(requestCall('can')))->toBe(['type' => 'boolean', 'optional' => false]);
});

it('keeps the Request rules off a resource, whose toArray() also takes a Request', function () {
    // A resource's committed output was inferred without these rules; seeding them there would move
    // it, so ResourceAstAnalyzer deliberately leaves requestVarNames empty for a JsonResource.
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve(requestCall('url')))->toBe(['type' => 'unknown', 'optional' => false]);
});

it('seeds requestVarNames for a non-resource subject from the analyzed method signature', function () {
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(StarterKitMiddleware::class), null, 'share');

    expect($analyzer->resolve(requestCall('url')))->toBe(['type' => 'string', 'optional' => false]);
});

it('types validated(key) from the form request rules', function () {
    $call = new MethodCall(new Variable('request'), 'validated', [new Arg(new String_('title'))]);

    expect((new KnownMethodRuleHandler)->resolve($call, formRequestScope(), requestRuleEngine()))
        ->toBe(['type' => 'string', 'optional' => false]);
});

it('types validated(key: ...) bound by name, not just by position', function () {
    $call = new MethodCall(new Variable('request'), 'validated', [
        new Arg(new String_('title'), name: new Identifier('key')),
    ]);

    expect((new KnownMethodRuleHandler)->resolve($call, formRequestScope(), requestRuleEngine()))
        ->toBe(['type' => 'string', 'optional' => false]);
});

// A bound `default` argument means data_get() can return that default instead of the rule's own
// type — conservatively declining beats confidently narrowing to the rule's type alone.
it('declines validated() when a default argument is also bound, named or positional', function () {
    $positional = new MethodCall(new Variable('request'), 'validated', [
        new Arg(new String_('title')), new Arg(new String_('fallback')),
    ]);
    $named = new MethodCall(new Variable('request'), 'validated', [
        new Arg(new String_('title')), new Arg(new String_('fallback'), name: new Identifier('default')),
    ]);

    expect((new KnownMethodRuleHandler)->resolve($positional, formRequestScope(), requestRuleEngine()))->toBeNull()
        ->and((new KnownMethodRuleHandler)->resolve($named, formRequestScope(), requestRuleEngine()))->toBeNull();
});

// getArgs() asserts !isFirstClassCallable() — reading through CallArguments must decline gracefully
// instead of fataling on $request->validated(...).
it('declines a first-class-callable validated(...) instead of fataling', function () {
    $call = new MethodCall(new Variable('request'), 'validated', [new VariadicPlaceholder]);

    expect((new KnownMethodRuleHandler)->resolve($call, formRequestScope(), requestRuleEngine()))->toBeNull();
});

it('declines validated() with a non-literal key, and on a plain Request', function () {
    $computed = new MethodCall(new Variable('request'), 'validated', [new Arg(new Variable('key'))]);
    $plain = new MethodCall(new Variable('request'), 'validated', [new Arg(new String_('title'))]);

    expect((new KnownMethodRuleHandler)->resolve($computed, formRequestScope(), requestRuleEngine()))->toBeNull()
        ->and((new KnownMethodRuleHandler)->resolve($plain, requestRuleScope(), requestRuleEngine()))->toBeNull();
});

// The zero-argument form returns the whole validated payload, not one key: synthesizing a shape
// for it is out of scope, so it declines exactly like a key the rules never mention.
it('declines validated() for a key the rules do not mention, and the zero-argument form', function () {
    $unknownKey = new MethodCall(new Variable('request'), 'validated', [new Arg(new String_('nope'))]);
    $wholePayload = new MethodCall(new Variable('request'), 'validated');

    expect((new KnownMethodRuleHandler)->resolve($unknownKey, formRequestScope(), requestRuleEngine()))->toBeNull()
        ->and((new KnownMethodRuleHandler)->resolve($wholePayload, formRequestScope(), requestRuleEngine()))->toBeNull();
});

it('declines validated() rather than letting a throwing rules() escape the analyzer', function () {
    $scope = new AnalysisScope(new ReflectionClass(stdClass::class));
    $scope->requestVarNames = ['request' => DynamicRequest::class];

    $call = new MethodCall(new Variable('request'), 'validated', [new Arg(new String_('name'))]);

    expect((new KnownMethodRuleHandler)->resolve($call, $scope, requestRuleEngine()))->toBeNull();
});

it('types auth()->user() and auth()->id()', function () {
    $call = fn (string $method): MethodCall => new MethodCall(new FuncCall(new Name('auth')), $method);

    expect((new KnownFunctionCallHandler)->resolve($call('user'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class])
        ->and((new KnownFunctionCallHandler)->resolve($call('id'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'number | null', 'optional' => false]);
});

it('declines an unbound ->user() on any other receiver', function () {
    $expr = new MethodCall(new FuncCall(new Name('resolve')), 'user');

    expect((new KnownFunctionCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))->toBeNull();
});

it('declines auth() with a named guard rather than answering with the default guard model', function () {
    // AuthUserResolver only reads auth.defaults.guard, so typing auth('admin')->user() as the web
    // guard's model would be confidently wrong — worse than the unknown a decline leaves.
    $named = new MethodCall(new FuncCall(new Name('auth'), [new Arg(new String_('admin'))]), 'user');
    $callable = new MethodCall(new FuncCall(new Name('auth'), [new VariadicPlaceholder]), 'user');

    expect((new KnownFunctionCallHandler)->resolve($named, requestRuleScope(), requestRuleEngine()))->toBeNull()
        ->and((new KnownFunctionCallHandler)->resolve($callable, requestRuleScope(), requestRuleEngine()))->toBeNull();
});

it('declines auth(guard: …) written by name, exactly like the positional guard', function () {
    $expr = new MethodCall(new FuncCall(new Name('auth'), [new Arg(new String_('admin'), name: new Identifier('guard'))]), 'user');

    expect((new KnownFunctionCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))->toBeNull();
});

it('declines Auth::user(...) as a first-class callable', function () {
    $expr = new StaticCall(new Name('Auth'), 'user', [new VariadicPlaceholder]);

    expect((new StaticCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))
        ->not->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class]);
});

it('types Auth::user() and Auth::id()', function () {
    $call = fn (string $method): StaticCall => new StaticCall(new Name('Auth'), $method);

    expect((new StaticCallHandler)->resolve($call('user'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class])
        ->and((new StaticCallHandler)->resolve($call('id'), requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'number | null', 'optional' => false]);
});

it('types config() with a literal key from the live configuration value', function (string $key, mixed $value, string $type) {
    config()->set($key, $value);

    $expr = new FuncCall(new Name('config'), [new Arg(new String_($key))]);

    expect((new KnownFunctionCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => $type, 'optional' => false]);
})->with([
    ['ts-publish-probe.name', 'Laravel', 'string'],
    ['ts-publish-probe.debug', true, 'boolean'],
    ['ts-publish-probe.retries', 3, 'number'],
    ['ts-publish-probe.ratio', 1.5, 'number'],
    ['ts-publish-probe.hosts', ['a'], 'unknown[]'],
    ['ts-publish-probe.missing', null, 'null'],
]);

// A default only applies when the key is unset, and answering `null` there is confidently wrong:
// the frontend gets a TS error on a value that is certainly the default. Type the default instead.
it('types config() with a default from that default when the key is unset', function (Expr $default, string $type) {
    $expr = new FuncCall(new Name('config'), [
        new Arg(new String_('ts-publish-probe.absent')),
        new Arg($default),
    ]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => $type, 'optional' => false]);
})->with([
    'string default' => [fn () => new String_('pk_test'), 'string'],
    'int default' => [fn () => new Int_(30), 'number'],
    'bool default' => [fn () => new ConstFetch(new Name('true')), 'boolean'],
    'unresolvable default' => [fn () => new Variable('fallback'), 'unknown'],
]);

it('still reads the live value when a key with a default is set', function () {
    config()->set('ts-publish-probe.present', 3);

    $expr = new FuncCall(new Name('config'), [
        new Arg(new String_('ts-publish-probe.present')),
        new Arg(new String_('fallback')),
    ]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'number', 'optional' => false]);
});

it('declines config() with a computed key', function () {
    $expr = new FuncCall(new Name('config'), [new Arg(new Variable('key'))]);

    expect((new KnownFunctionCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))->toBeNull();
});

it('reads config() arguments by name before position', function () {
    config()->set('ts-publish-test.named', 'live');

    $call = new FuncCall(new Name('config'), [
        new Arg(new ConstFetch(new Name('true')), name: new Identifier('default')),
        new Arg(new String_('ts-publish-test.named'), name: new Identifier('key')),
    ]);

    expect((new KnownFunctionCallHandler)->resolve($call, requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'string', 'optional' => false]);
});

it('types a key explicitly set to null as null even when a default is supplied', function () {
    config()->set('ts-publish-test.nulled', null);

    $call = new FuncCall(new Name('config'), [new Arg(new String_('ts-publish-test.nulled')), new Arg(new String_('fallback'))]);

    expect((new KnownFunctionCallHandler)->resolve($call, requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'null', 'optional' => false]);
});

it('types a single-argument config() on an absent key as null', function () {
    $call = new FuncCall(new Name('config'), [new Arg(new String_('ts-publish-test.absent'))]);

    expect((new KnownFunctionCallHandler)->resolve($call, requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'null', 'optional' => false]);
});

it('types the typed config accessors from their declared return type, whatever the key or default', function (string $method, string $type) {
    $expr = new MethodCall(new FuncCall(new Name('config')), $method, [
        new Arg(new String_('ts-publish-probe.anything')),
        new Arg(new Int_(0)),
    ]);

    expect((new KnownFunctionCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => $type, 'optional' => false]);
})->with([
    ['string', 'string'],
    ['integer', 'number'],
    ['float', 'number'],
    ['boolean', 'boolean'],
    ['array', 'unknown[]'],
]);

it('types config()->get() exactly like config()', function () {
    config()->set('ts-publish-probe.via-get', 'live');

    $expr = new MethodCall(new FuncCall(new Name('config')), 'get', [new Arg(new String_('ts-publish-probe.via-get')), new Arg(new String_('fallback'))]);

    expect((new KnownFunctionCallHandler)->resolve($expr, requestRuleScope(), requestRuleEngine()))
        ->toBe(['type' => 'string', 'optional' => false]);
});

it('declines a typed accessor on a config() receiver that already took a key, and an unknown accessor', function () {
    $onValue = new MethodCall(new FuncCall(new Name('config'), [new Arg(new String_('a.b'))]), 'integer', [new Arg(new String_('c'))]);
    $unknown = new MethodCall(new FuncCall(new Name('config')), 'nope', [new Arg(new String_('c'))]);

    expect((new KnownFunctionCallHandler)->resolve($onValue, requestRuleScope(), requestRuleEngine()))->toBeNull()
        ->and((new KnownFunctionCallHandler)->resolve($unknown, requestRuleScope(), requestRuleEngine()))->toBeNull();
});

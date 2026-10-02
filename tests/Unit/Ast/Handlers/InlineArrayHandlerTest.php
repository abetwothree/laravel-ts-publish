<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\NestedResourceSpreadResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved the
 * spread arm from scope bindings alone, without recursing into a sub-expression.
 */
function inlineArrayHandlerThrowingEngine(): ExpressionEngine
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
 * A stub engine whose returnArrayAnalysis() returns a canned analysis for the exact array passed
 * in, standing in for ResourceAstAnalyzer::analyzeReturnArray()'s real per-key resolution.
 */
final class InlineArrayHandlerReturnArrayStubEngine implements ExpressionEngine
{
    public function __construct(private Array_ $expectedArray, private ResourceAnalysis $analysis) {}

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
        if ($array !== $this->expectedArray) {
            throw new RuntimeException('Unexpected array passed to InlineArrayHandlerReturnArrayStubEngine');
        }

        return $this->analysis;
    }
}

it('resolves an inline array with a nested key and a model spread arm to an Omit<> intersection', function () {
    // Mirrors NestedResourceSpreadResource::$members_model_spread's shape — a sibling key plus
    // ...$member->toArray() — proving the moved analyzeInlineArray()/collectInlineArraySpreadArms()
    // pair still Omit<>'s the explicit key against the model arm without engine recursion.
    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('note')),
        new ArrayItem(new MethodCall(new Variable('member'), 'toArray'), null, unpack: true),
    ]);

    $analysis = new ResourceAnalysis(properties: [
        ['name' => 'note', 'type' => 'string', 'optional' => false, 'description' => ''],
    ]);

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $scope->varModelBindings['member'] = User::class;

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($result)->toBe([
        'type' => "Omit<User, 'note'> & { note: string }",
        'optional' => false,
        'embeddedModelFqcns' => [User::class],
    ]);
});

it('declines a non-array expression', function () {
    $expr = new Variable('foo');
    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $result = (new InlineArrayHandler)->resolve($expr, $scope, inlineArrayHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('keeps both arms of a nested mixed EnumResource ternary that rendered the same string', function () {
    // Mirrors TeamStatusAuditResource::$audit's inner key: both arms read the same list-shaped
    // accessor, so the union merge collapsed them to one member before the enum rewrite ran. Only
    // the per-arm shape still says the [] belongs on both.
    config()->set('ts-publish.enums.use_tolki_package', true);

    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('history')),
    ]);

    $analysis = new ResourceAnalysis(
        properties: [
            ['name' => 'history', 'type' => 'StatusType[]', 'optional' => false, 'description' => ''],
        ],
        enumResources: ['history' => Status::class],
        directEnumFqcns: ['history' => Status::class],
        enumResourceArmShapes: ['history' => ['wrapIsCollection' => true, 'directIsArray' => true]],
    );

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($result)->toBe([
        'type' => '{ history: AsEnum<typeof Status>[] | StatusType[] }',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class],
        'embeddedEnumResourceFqcns' => [Status::class],
    ]);
});

it('carries a nested mixed ternary\'s remaining union members past the two synthesised arms', function () {
    // A nullable direct arm leaves `null` as a member the two synthesised arms do not account for:
    // it survives only by being carried over, where the top-level twin re-appends it from its own
    // recorded nullability instead.
    config()->set('ts-publish.enums.use_tolki_package', true);

    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('history')),
    ]);

    $analysis = new ResourceAnalysis(
        properties: [
            ['name' => 'history', 'type' => 'StatusType[] | null', 'optional' => false, 'description' => ''],
        ],
        enumResources: ['history' => Status::class],
        directEnumFqcns: ['history' => Status::class],
        enumResourceArmShapes: ['history' => ['wrapIsCollection' => true, 'directIsArray' => true]],
    );

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($result['type'])->toBe('{ history: AsEnum<typeof Status>[] | StatusType[] | null }');
});

it('claims no type import for a nested mixed enum whose bare name the rewrite spelled away', function () {
    // The armShape-less fallback, reachable for any mixed pair TernaryHandler never attributed (a
    // `??`, say): the collapsed member is substituted outright, so the bare StatusType token is gone
    // from the emitted type and must not claim a type import the transformer would emit unused.
    config()->set('ts-publish.enums.use_tolki_package', true);

    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('history')),
    ]);

    $analysis = new ResourceAnalysis(
        properties: [
            ['name' => 'history', 'type' => 'StatusType[]', 'optional' => false, 'description' => ''],
        ],
        enumResources: ['history' => Status::class],
        directEnumFqcns: ['history' => Status::class],
    );

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($result)->toBe([
        'type' => '{ history: AsEnum<typeof Status>[] }',
        'optional' => false,
        'embeddedEnumResourceFqcns' => [Status::class],
    ]);
});

it('queues one resource per token in the order the object spells them, never deduped', function () {
    // Mirrors ImageReviewResource::$review: `subject` is a morph union over two resources that share a name, and
    // `again` names one of them a second time, so each occurrence needs its own entry for aliasing to walk.
    $crm = 'Workbench\\Crm\\Http\\Resources\\UserResource';
    $app = 'Workbench\\App\\Http\\Resources\\UserResource';

    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('subject')),
        new ArrayItem(new Variable('placeholder'), new String_('again')),
        new ArrayItem(new Variable('placeholder'), new String_('label')),
    ]);

    $analysis = new ResourceAnalysis(
        properties: [
            ['name' => 'subject', 'type' => 'UserResource | UserResource', 'optional' => false, 'description' => ''],
            ['name' => 'again', 'type' => 'UserResource', 'optional' => false, 'description' => ''],
            ['name' => 'label', 'type' => 'string', 'optional' => false, 'description' => ''],
        ],
        nestedResources: [$crm => $crm, $app => $app, 'again' => $crm],
        inlineResourceFqcns: ['subject' => [$crm, $app]],
    );

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    $result = (new InlineArrayHandler)->resolve($array, $scope, new InlineArrayHandlerReturnArrayStubEngine($array, $analysis));

    expect($result)->toBe([
        'type' => '{ subject: UserResource | UserResource; again: UserResource; label: string }',
        'optional' => false,
        'embeddedResourceFqcns' => [$crm, $app, $crm],
    ]);
});

/**
 * The classes an inline array queues on each channel when its members arrive as the engine records them. A member is
 * its type, then the class behind each of its tokens on the model channel, as its own union queued them, and then the
 * same on the resource channel when it queues any.
 *
 * @param  array<string, array{0: string, 1: list<class-string>, 2?: list<class-string>}>  $members
 * @return array{list<class-string>, list<class-string>} the models, then the resources
 */
function inlineArrayQueues(array $members): array
{
    $array = new Array_(array_map(
        static fn (string $name): ArrayItem => new ArrayItem(new Variable('placeholder'), new String_($name)),
        array_keys($members),
    ));

    $analysis = new ResourceAnalysis(
        properties: array_map(
            static fn (string $name, array $member): array => ['name' => $name, 'type' => $member[0], 'optional' => false, 'description' => ''],
            array_keys($members),
            $members,
        ),
        inlineModelFqcns: array_map(static fn (array $member): array => $member[1], $members),
        inlineResourceFqcns: array_map(static fn (array $member): array => $member[2] ?? [], $members),
    );

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $result = (new InlineArrayHandler)->resolve($array, $scope, new InlineArrayHandlerReturnArrayStubEngine($array, $analysis));

    return [$result['embeddedModelFqcns'] ?? [], $result['embeddedResourceFqcns'] ?? []];
}

// A merge by text can queue a class more or less often than the member spells it. The next member's tokens would read
// that difference, so each member hands on one entry per token.
it('hands on one entry per token for every name a member gives a single class', function (array $members, array $models, array $resources = []) {
    expect(inlineArrayQueues($members))->toBe([$models, $resources]);
})->with([
    'a class its union queued twice behind one token' => [
        ['who' => ['{ k: User | null; n: number }', [User::class, User::class]]],
        [User::class],
    ],
    'a class its union queued once behind two tokens' => [
        ['crm' => ['User | User[] | null', [CrmUser::class]]],
        [CrmUser::class, CrmUser::class],
    ],
    'a class queued once for every token it has' => [
        ['one' => ['User | null', [User::class]], 'list' => ['User[]', [CrmUser::class]]],
        [User::class, CrmUser::class],
    ],
    'a shortfall beside a name that has an entry of its own' => [
        ['m' => ['Post | User | User[]', [Post::class, User::class]]],
        [Post::class, User::class, User::class],
    ],
    'a member of each kind, one after the other' => [
        [
            'who' => ['{ k: User | null; n: number }', [User::class, User::class]],
            'crm' => ['User | User[] | null', [CrmUser::class]],
        ],
        [User::class, CrmUser::class, CrmUser::class],
    ],
    'a model and a resource under different names, each filled on its own' => [
        ['m' => ['Address | Address[] | UserResource', [Address::class], [UserResource::class]]],
        [Address::class, Address::class],
        [UserResource::class],
    ],
    'a name that another member queues on both channels' => [
        [
            'a' => ['{ raw: Address | null; address: Address }', [Address::class], [AddressResource::class]],
            'b' => ['Address | Address[]', [Address::class]],
        ],
        [Address::class, Address::class, Address::class],
        [AddressResource::class],
    ],
    'a resource its union queued once behind two tokens' => [
        ['x' => ['UserResource | UserResource[]', [], [UserResource::class]]],
        [],
        [UserResource::class, UserResource::class],
    ],
    'a resource counted by the name it publishes under' => [
        ['x' => ['Address | Address[]', [], [AddressResource::class]]],
        [],
        [AddressResource::class, AddressResource::class],
    ],
]);

// A model and a resource that publish under one name are two classes behind it, one on each channel. The channels are
// merged, so a fill on both would read the model on every token.
it('keeps the queue of a name that a member queues on both channels, one class on each', function (array $members, array $models, array $resources) {
    expect(inlineArrayQueues($members))->toBe([$models, $resources]);
})->with([
    'one entry each for the two tokens' => [
        ['data' => ['{ raw: Address | null; address: Address }', [Address::class], [AddressResource::class]]],
        [Address::class],
        [AddressResource::class],
    ],
]);

// An entry for a class the member's type does not spell is left as it came: this rule does not correct it.
it('leaves the entry for a class its member does not spell', function (array $members, array $models, array $resources = []) {
    expect(inlineArrayQueues($members))->toBe([$models, $resources]);
})->with([
    'a model' => [
        ['m' => ['string | null', [User::class]]],
        [User::class],
    ],
    'a resource' => [
        ['m' => ['User | null', [], [UserResource::class]]],
        [],
        [UserResource::class],
    ],
]);

it('keeps the queue of a member that gives one name two classes, whatever its tokens', function (array $members, array $models, array $resources = []) {
    expect(inlineArrayQueues($members))->toBe([$models, $resources]);
})->with([
    'two models, one entry for each token' => [
        ['either' => ['User | User | null', [User::class, CrmUser::class]]],
        [User::class, CrmUser::class],
    ],
    'two models behind a single token' => [
        ['either' => ['User[] | null', [User::class, CrmUser::class]]],
        [User::class, CrmUser::class],
    ],
    'two resources, one entry for each token' => [
        ['x' => ['UserResource | UserResource', [], [UserResource::class, CrmUserResource::class]]],
        [],
        [UserResource::class, CrmUserResource::class],
    ],
]);

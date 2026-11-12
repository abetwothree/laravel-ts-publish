<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\UnreadSpreads;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SpreadLocalModelResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Status;
use Workbench\App\Events\ManifestAssembled;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\NestedResourceSpreadResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Comment;
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

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
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

    public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    /** Reads every value as `json_encode()` sends it. */
    public function reading(): Reading
    {
        return Reading::Encoded;
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
    // ...$member->toArray() — proving planArms() classifies the model arm without engine recursion, and the
    // print Omit<>'s the explicit key against the arm the literal's fold hands it.
    $array = new Array_([
        new ArrayItem(new Variable('placeholder'), new String_('note')),
        new ArrayItem(new MethodCall(new Variable('member'), 'toArray'), null, unpack: true),
    ]);

    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $scope->varModelBindings['member'] = User::class;

    $plan = (new InlineArrayHandler)->planArms($array->items, $scope, inlineArrayHandlerThrowingEngine());

    $analysis = new ResourceAnalysis(properties: [
        ['name' => 'note', 'type' => 'string', 'optional' => false, 'description' => ''],
    ]);
    $analysis->setSpreadArms(array_column($plan->printed, 'arm'));

    $engine = new InlineArrayHandlerReturnArrayStubEngine($array, $analysis);

    $result = (new InlineArrayHandler)->resolve($array, $scope, $engine);

    expect($plan->printed)->toBe([1 => ['arm' => ['fqcn' => User::class, 'isModel' => true, 'isCollection' => false], 'map' => null]]);

    // The spread's keys are no string or int the literal writes, so the result marks them unnamed for array_keys().
    expect($result)->toBe([
        'type' => "Omit<User, 'note'> & { note: string }",
        'optional' => false,
        'embeddedModelFqcns' => [User::class],
        'unnamedKeys' => true,
    ]);
});

/**
 * An engine that types `$arm` as a resource, `$obj` and any literal as `{ a: number }`, `$map` as a string map, and
 * `$maybe` and `$maybeObj` as a number map or `{ a: number }` beside `[]`.
 */
function inlineArrayHandlerArmEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            return match (true) {
                $expr instanceof Variable && $expr->name === 'arm' => ['type' => 'UserResource', 'optional' => false, 'resourceFqcn' => UserResource::class],
                $expr instanceof Variable && $expr->name === 'map' => ['type' => 'Record<string, string>', 'optional' => false],
                $expr instanceof Variable && $expr->name === 'maybe' => ['type' => 'Record<string, number> | never[]', 'optional' => false],
                $expr instanceof Variable && $expr->name === 'maybeObj' => ['type' => '{ a: number } | never[]', 'optional' => false],
                default => ['type' => '{ a: number }', 'optional' => false],
            };
        }

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

// A print places every arm before the keys, so a spread a later arm follows stays an arm, though written in place.
it('plans the spreads a nested literal prints as arms, by item, with each map\'s value, checked after another item', function (string $php, int $items, array $printed) {
    $array = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    assert($array instanceof Array_);
    $plan = (new InlineArrayHandler)->planArms($array->items, new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class)), inlineArrayHandlerArmEngine());

    expect($plan->items)->toHaveCount($items)
        ->and(array_map(static fn (array $arm): string => ($arm['arm']['type'] ?? $arm['arm']['fqcn'])
            .($arm['map'] === null ? '' : ($arm['map']['checksKeys'] ? ' fits ' : ' holds ').$arm['map']['value']['type'])
            .(($arm['map']['mayBeEmpty'] ?? false) ? ' or none' : ''), $plan->printed))
        ->toBe($printed);
})->with([
    'a value an arm follows' => ['[...$obj, ...$arm]', 2, [0 => '{ a: number }', 1 => UserResource::class]],
    'a value after the arm, written in place' => ['[...$arm, ...$obj]', 2, [0 => UserResource::class]],
    'a literal an arm follows' => ['[...["a" => 1], ...$arm]', 2, [0 => '{ a: number }', 1 => UserResource::class]],
    'a literal no arm follows, inlined' => ['[...["a" => 1], "b" => 2]', 2, []],
    'a map after a key' => ['["b" => 2, ...$map]', 2, [1 => 'Record<string, string> fits string']],
    'a map first' => ['[...$map, "b" => 2]', 2, [0 => 'Record<string, string> holds string']],
    'a map after a map' => ['[...$map, ...$map]', 2, [0 => 'Record<string, string> holds string', 1 => 'Record<string, string> fits string']],
    'a map or [] before a map, as the map' => ['[...$maybe, ...$map]', 2, [0 => 'Record<string, number> holds number or none', 1 => 'Record<string, string> fits string']],
    'an object or [], no arm' => ['[...$maybeObj, "b" => 2]', 2, []],
    'a helper the item loop flattens' => ['[...$this->helper(), ...$arm]', 2, [1 => UserResource::class]],
]);

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
        'keepsKeys' => true,
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
        'keepsKeys' => true,
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
        'keepsKeys' => true,
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
    'a name of its own filled beside one the member queues on both channels' => [
        ['m' => ['Post | Post[] | Address[] | Address', [Post::class, Address::class], [AddressResource::class]]],
        [Post::class, Address::class, Post::class],
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

/** The type one expression resolves to on a resource subject, or on a plain class when $subject names one. */
function inlineListType(string $php, ?string $subject = null): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $analyzer = $subject === null
        ? new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)
        : new ResourceAstAnalyzer(new ReflectionClass($subject));

    return $analyzer->resolve($expr)['type'];
}

it('types a keyless list literal as the list it encodes as, as collect() does', function (string $php, string $type) {
    expect(inlineListType($php))->toBe($type)
        ->and(inlineListType('collect('.$php.')'))->toBe($type);
})->with([
    'a constant list' => ['["a", "b"]', 'string[]'],
    'a mixed constant list' => ['[1, "a"]', '(number | string)[]'],
    'a constant list of lists' => ['[[1, 2], [3]]', 'number[][]'],
    'a constant list of records' => ['[["a" => 1], ["a" => 2]]', '{ a: number }[]'],
    'enum cases' => ['[\\'.Status::class.'::Draft, \\'.Status::class.'::Published]', 'StatusType[]'],
    'a global constant and an enum value' => ['[PHP_INT_MAX, \\'.Status::class.'::Published->value]', 'number[]'],
    'a list of reads' => ['[$this->id, $this->title]', '(number | string)[]'],
    'a list of shapes' => ['[["a" => $this->id], ["b" => $this->title]]', '({ a: number } | { b: string })[]'],
    'a spread of a list' => ['[...explode(",", $this->title), 1]', '(string | number)[]'],
    'a spread of a constant list' => ['[...["a"], "b"]', 'string[]'],
    'an element no rule types' => ['[$this->id, json_decode("{}")]', 'unknown[]'],
]);

// A resource's filter keeps every key beside a named one, so it rewrites only the members, and only those that list.
it('keeps a keyed literal\'s keys when no member the re-index could rewrite drops them', function (string $php, bool $keeps) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $result = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr);

    expect($result['keepsKeys'] ?? false)->toBe($keeps);
})->with([
    'a collection under a named key' => ['["inner" => collect([6 => $this->title])]', true],
    'scalars under named keys' => ['["a" => 1, "b" => $this->title]', true],
    'only numeric keys, which the filter lists' => ['[6 => collect([6 => $this->title])]', false],
    'an array member beside a collection' => ['["a" => collect([6 => 1]), "b" => [6 => 2]]', false],
    'a spread of a literal, whose keys it names' => ['[...["a" => 1], "b" => 2]', true],
    'a spread of a value, whose keys it does not' => ['[...$this->options, "b" => 2]', false],
]);

it('keeps a missing when() element out of a list, which a resource re-indexes', function () {
    expect(inlineListType('[$this->when($this->is_pinned, "pinned"), "x"]'))->toBe('string[]');
});

it('types a list nested in a keyed literal, and a resource list, with its import', function () {
    $expr = new AstParser()->parseSource('<?php ["tags" => ["a"], "authors" => [new \\'.UserResource::class.'($this->author)]];')[0]->expr;
    $result = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr);

    expect($result['type'])->toBe('{ tags: string[]; authors: UserResource[] }')
        ->and($result['embeddedResourceFqcns'] ?? [])->toBe([UserResource::class]);
});

it('types a list literal the same on a subject that is no resource', function (string $php, string $type) {
    expect(inlineListType($php, ManifestAssembled::class))->toBe($type);
})->with([
    'a constant list' => ['["a", "b"]', 'string[]'],
    'a list of reads' => ['[$this->parcelId, $this->carrier]', '(number | string)[]'],
]);

it('leaves a literal that is no list to the record path', function (string $php, string $type) {
    expect(inlineListType($php))->toBe($type);
})->with([
    'an empty literal' => ['[]', 'never[]'],
    'a spread of a reflected array, whose keys are unknown' => ['[...$this->resource->toArray(), "x"]', 'Record<string, unknown>'],
    'a merge() call, which a resource merges into the array' => ['[$this->merge(["a" => 1])]', '{ a: number }'],
]);

// json_encode() writes a list only when the keys run 0 to n-1 in order; a resource's re-index is its publisher's.
it('types a literal whose keys run 0 to n-1 as a list, and any other literal by the keys JSON writes', function (string $php, string $type) {
    expect(inlineListType($php, ManifestAssembled::class))->toBe($type);
})->with([
    'int keys from 0 in order' => ['[0 => "a", 1 => "b"]', 'string[]'],
    'numeric-string keys from 0' => ['["0" => "a", "1" => 2]', '(string | number)[]'],
    'a key written twice keeps its last value' => ['[0 => "a", "0" => 1]', 'number[]'],
    'int keys out of order' => ['[1 => "a", 0 => "b"]', '{ "1": string; "0": string }'],
    'an int key not from 0' => ['[42 => "a"]', '{ "42": string }'],
    'a numeric-string key PHP keeps as a string' => ['["06" => "a"]', '{ "06": string }'],
    'a named key beside an int key' => ['[0 => "a", "b" => 1]', '{ "0": string; b: number }'],
    'reads keyed 0 to n-1' => ['[0 => $this->parcelId, "1" => $this->carrier]', '(number | string)[]'],
    'a read written twice keeps its last value' => ['[0 => $this->parcelId, "0" => $this->carrier]', 'string[]'],
    'a keyless read after an int key, which PHP keys 1' => ['[0 => $this->parcelId, $this->carrier]', '(number | string)[]'],
    'reads keyed out of order' => ['[1 => $this->parcelId, 0 => $this->carrier]', '{ "1": number; "0": string }'],
]);

// O5: a spread of an object-typed value joins as an arm of its own type, where it was left out.
it('keeps an arm for a spread whose value types as one object', function (string $php, string $type) {
    expect(inlineListType($php))->toBe($type);
})->with([
    'an array cast' => ['[...$this->options, "a" => 1]', "Omit<Record<string, string>, 'a'> & { a: number }"],
    // The runtime also sends the keyless item, as "0" (Follow-Up F11).
    'a record beside a keyless item' => ['[...["k" => 1], "x"]', '{ k: number }'],
]);

it('carries the import an object-typed spread arm names', function () {
    $expr = new AstParser()->parseSource('<?php [...$this->author->only(["id"]), "x" => 1];')[0]->expr;
    $result = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class)->resolve($expr);

    expect($result['type'])->toBe("Omit<Pick<User, 'id'>, 'x'> & { x: number }")
        ->and($result['embeddedModelFqcns'] ?? [])->toBe([User::class]);
});

// The loaded relation's or the mapped element's model is no guess for another variable: Laravel spreads Ada's columns.
it('spreads the model a variable no closure binds holds, never the relation\'s', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    new Comment()->forceFill(['id' => 1, 'content' => 'alpha', 'post_id' => $post->id, 'user_id' => $user->id])->save();

    /** @var array{data: array<string, mixed>} $sent */
    $sent = json_decode((string) new SpreadLocalModelResource(Post::with(['author', 'comments.user'])->findOrFail($post->id))
        ->response(request())->getContent(), true);

    expect(publishedResourceKeyTypes(SpreadLocalModelResource::class, Post::class))->toBe([
        'local?' => "Omit<User, 'x'> & { x: number }",
        'captured?' => "Omit<User, 'x'> & { x: number }",
        'collected_local' => "(Omit<User, 'x'> & { x: number })[]",
        'relation_local' => "(Omit<User, 'x'> & { x: number })[]",
        'fresh?' => "Omit<Record<string, unknown>, 'x'> & { x: number }",
        'unbound?' => '{ x: number }',
    ])
        ->and($sent['data']['local'])->toMatchArray(['name' => 'Ada', 'x' => 1])
        ->and($sent['data']['captured'])->toMatchArray(['name' => 'Ada', 'x' => 1])
        ->and($sent['data']['collected_local'][0])->toMatchArray(['name' => 'Ada', 'x' => 1])
        ->and($sent['data']['relation_local'][0])->toMatchArray(['name' => 'Ada', 'x' => 1])
        ->and($sent['data']['fresh'])->toBe(['x' => 1])
        ->and($sent['data'])->not->toHaveKey('unbound');
});

/**
 * An engine typing every value as $type, as a read in the gap mode may: gap arms beside an object, or its open marker.
 */
function inlineArrayHandlerGappedEngine(string $type): ExpressionEngine
{
    return new class($type) implements ExpressionEngine
    {
        /** Type every value alike. */
        public function __construct(private readonly string $type) {}

        /** Type every value as the engine's one type. */
        public function resolve(Expr $expr): array
        {
            return ['type' => $this->type, 'optional' => false];
        }

        /** Fails the test: no method is spread in this case. */
        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
        }

        /** Fails the test: no array is analyzed in this case. */
        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

it('reads a spread value past its gap arms and open marker: its named keys in place, a map\'s value, an object arm', function (string $type, string $arm) {
    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $value = new Variable('held');
    $result = ['type' => $type, 'optional' => false];
    $keys = InlineArrayHandler::spreadKeys($value, $result, $scope);

    expect(array_map(static fn (array $key): string => $key[0].($key[1]['optional'] ? '?' : '').': '.$key[1]['type'], $keys ?? []))
        ->toBe(['by: string | $gap2'])
        ->and(InlineArrayHandler::classifySpreadArm($value, $scope, inlineArrayHandlerGappedEngine($type))['type'] ?? null)
        ->toBe($arm);
})->with([
    'a gap beside the object' => ['{ by: string | $gap2 } | $gap1', '{ by: string | $gap2 }'],
    'the open marker' => ['{ by: string | $gap2; #$open: unknown }', '{ by: string | $gap2; #$open: unknown }'],
    'both, beside null' => ['{ by: string | $gap2; #$open: unknown } | null | $gap1', '{ by: string | $gap2; #$open: unknown }'],
]);

it('reads a map spread\'s value past its gap arms and open marker', function (string $type) {
    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));

    expect(InlineArrayHandler::spreadSignature(new Variable('held'), ['type' => $type, 'optional' => false], $scope)['type'] ?? null)
        ->toBe('string');
})->with([
    'a record beside a gap' => ['Record<string, string> | $gap1'],
    'a signature beside the marker' => ['{ [key: string]: string; #$open: unknown }'],
]);

it('opens the host for a value its marker opens, as for its mark, and neither for a gap; each counts as the other mode', function (array $result, bool $opens, int $counts) {
    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $writes = new LiteralWrites;
    $unread = UnreadSpreads::count();
    $inPlace = InlineArrayHandler::recordSpread($writes, new Variable('held'), ['optional' => false, ...$result], $scope, 0);
    $folded = new MethodAnalysis;
    $writes->applyTo($folded, false);

    expect($inPlace)->toBeTrue()
        ->and(array_column($folded->properties, 'name'))->toBe(['by'])
        ->and($folded->holdsUnnamedKeys)->toBe($opens)
        ->and(UnreadSpreads::count() - $unread)->toBe($counts);
})->with([
    'the open marker' => [['type' => '{ by: string; #$open: unknown }'], true, 0],
    'its mark, as the gap mode off reads it' => [['type' => '{ by: string }', 'openReadings' => ["\0{ by: string }"]], true, 0],
    'the marker beside a signature, which reads open' => [['type' => '{ by: string; [key: string]: number; #$open: unknown }'], true, 1],
    'its mark beside a signature' => [['type' => '{ by: string; [key: string]: number }', 'openReadings' => ["\0{ by: string; [key: string]: number }"]], true, 1],
    'a gap arm' => [['type' => '{ by: string } | $gap1'], false, 0],
]);

it('keeps a gap in the gap mode where a union of objects spread leaves out a value no rule types (PJ11b)', function (bool $shows) {
    $scope = new AnalysisScope(new ReflectionClass(NestedResourceSpreadResource::class));
    $read = static fn (): ?array => InlineArrayHandler::spreadKeys(new Variable('held'), ['type' => '{ k: string } | { k: unknown }', 'optional' => false], $scope);
    $keys = $shows ? resolve(AnalysisMemo::class)->showingGaps($read) : $read();
    $type = $keys[0][1]['type'] ?? '';

    expect(VisibleGaps::stripped($type)[0])->toBe('string')
        ->and(VisibleGaps::ids($type))->toHaveCount($shows ? 1 : 0);
})->with([false, true]);

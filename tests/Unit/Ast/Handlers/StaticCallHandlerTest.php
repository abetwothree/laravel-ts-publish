<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\AnnulledResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\EnumResourceShadowedParameterResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Controllers\InertiaSingleResourceController;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\EnumCollectionResource;
use Workbench\App\Http\Resources\EventLogResource;
use Workbench\App\Http\Resources\FluentSelfResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ReceiverMethodResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Activity;
use Workbench\App\Models\Address;
use Workbench\App\Models\Category;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\TrackingEvent;
use Workbench\App\Models\Venue;
use Workbench\App\Models\Warehouse;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function staticCallHandlerThrowingEngine(): ExpressionEngine
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
 * The type one expression resolves to through the full resource profile of FluentSelfResource over a Category.
 */
function staticCallHandlerResolveOnCategory(string $php): string
{
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    return new ResourceAstAnalyzer(new ReflectionClass(FluentSelfResource::class), Category::class)->resolve($expr)['type'];
}

/**
 * A stub engine resolving each distinct expression instance to its own canned result, keyed by
 * object identity — mirrors ClosureHandlerArmStubEngine's convention for the same reason.
 */
final class StaticCallHandlerArmStubEngine implements ExpressionEngine
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

        throw new RuntimeException('Unexpected expression passed to StaticCallHandlerArmStubEngine');
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
 * A resource whose constructor names its payload parameter something other than `resource` —
 * pins that resourcePayloadArguments() reflects the concrete receiver's own constructor, not
 * JsonResource's, so a subclass departing from the base parameter name still resolves.
 */
final class NamedPayloadResource extends JsonResource
{
    public function __construct(mixed $payload)
    {
        parent::__construct($payload);
    }
}

/**
 * A collection that collects no class the run can name, so `new` and `make()` on it reach the plain resource branch.
 */
final class StaticCallHandlerOrphanCollection extends ResourceCollection {}

/**
 * A resource that returns early when its parent is null, so only the return after the guard wraps a parent it proves.
 * The spread helper runs inside the exit, where the early exit's proof does not reach, and under a when() guard.
 *
 * @mixin Category
 */
final class StaticCallHandlerEarlyExitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->parent === null) {
            return ['inside' => CategoryResource::make($this->parent), ...$this->spreadParent()];
        }

        return [
            'after' => CategoryResource::make($this->parent),
            'guarded' => $this->when($this->parent, fn () => [...$this->spreadParent()]),
        ];
    }

    /** @return array<string, mixed> */
    public function spreadParent(): array
    {
        return ['spread' => CategoryResource::make($this->parent)];
    }
}

// ToResourceHandler

it('declines a method call named neither toResource nor toResourceCollection', function () {
    $expr = new MethodCall(new Variable('this'), 'somethingElse');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new ToResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('reads toResource(resourceClass: …) and toResourceCollection(resourceClass: …) by name', function () {
    $explicit = fn (): Arg => new Arg(new ClassConstFetch(new Name(PostResource::class), 'class'), name: new Identifier('resourceClass'));
    $single = new MethodCall(new Variable('model'), 'toResource', [$explicit()]);
    $many = new MethodCall(new Variable('models'), 'toResourceCollection', [$explicit()]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    expect((new ToResourceHandler)->resolve($single, $scope, staticCallHandlerThrowingEngine()))
        ->toBe(['type' => 'PostResource', 'optional' => false, 'resourceFqcn' => PostResource::class])
        ->and((new ToResourceHandler)->resolve($many, $scope, staticCallHandlerThrowingEngine()))
        ->toBe(['type' => 'PostResource[]', 'optional' => false, 'resourceFqcn' => PostResource::class]);
});

// The decline is all-or-nothing on purpose: Venue resolves to VenueResource and Activity resolves to
// nothing, so a partial union would publish VenueResource for a property that can hold an Activity.
it('declines a morph union receiver when any target has no resource class', function () {
    $expr = new MethodCall(new Variable('subject'), 'toResource');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $scope->varClassBindings['subject'] = [Venue::class, Activity::class];

    $result = (new ToResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
});

// Identical resource FQCNs are the only thing the dedupe can collapse, so two arms holding the same
// model are exactly its trigger: the union must spell EventLogResource once, not twice.
it('renders one token when two morph union targets resolve to the same resource class', function () {
    $expr = new MethodCall(new Variable('subject'), 'toResource');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $scope->varClassBindings['subject'] = [TrackingEvent::class, TrackingEvent::class];

    $result = (new ToResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'EventLogResource',
        'optional' => false,
        'embeddedResourceFqcns' => [EventLogResource::class],
    ]);
});

// StaticCallHandler

it('resolves PostResource::collection($this->posts) to the resource channel with a [] type', function () {
    $expr = new StaticCall(new Name(PostResource::class), 'collection', [
        new Arg(new PropertyFetch(new Variable('this'), 'posts')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'PostResource[]',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
    ]);
});

it('resolves EnumResource::make($this->status) to the enum channel', function () {
    $expr = new StaticCall(new Name(EnumResource::class), 'make', [
        new Arg(new PropertyFetch(new Variable('this'), 'status')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'StatusType',
        'optional' => false,
        'enumFqcn' => Status::class,
    ]);
});

// A local assigned once is followed to what it holds; any other payload is resolved, and kept when it holds one enum.
it('wraps the one enum a resolved payload holds', function (string $php, array $expected) {
    $parse = fn (string $source): Expr => new AstParser()->parseSource('<?php '.$source.';')[0]->expr;
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->localVarBindings = [
        'status' => $parse('$this->status'),
        'visibility' => $parse('$this->visibility'),
        'draft' => $parse('\\'.Status::class.'::Draft'),
        'rating' => $parse('$this->rating'),
        'loop' => $parse('$loop'),
    ];

    $result = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope)->resolve($parse($php));

    expect($result)->toBe($expected);
})->with([
    'make(), a local' => ['\\'.EnumResource::class.'::make($status)', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'new, a nullable local' => ['new \\'.EnumResource::class.'($visibility)', ['type' => 'VisibilityType | null', 'optional' => false, 'enumFqcn' => Visibility::class]],
    'make(), a local holding a case' => ['\\'.EnumResource::class.'::make($draft)', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'make(), a coalesce' => ['\\'.EnumResource::class.'::make($this->status ?? \\'.Status::class.'::Draft)', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'make(), a local holding no enum' => ['\\'.EnumResource::class.'::make($rating)', ['type' => 'unknown', 'optional' => false]],
    'make(), a local bound to itself' => ['\\'.EnumResource::class.'::make($loop)', ['type' => 'unknown', 'optional' => false]],
    'new, a coalesce' => ['new \\'.EnumResource::class.'($this->status ?? \\'.Status::class.'::Draft)', ['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]],
    'make(), a coalesce that can be null' => ['\\'.EnumResource::class.'::make($this->visibility ?? null)', ['type' => 'VisibilityType | null', 'optional' => false, 'enumFqcn' => Visibility::class]],
    'make(), an enum beside a string' => ['\\'.EnumResource::class.'::make($this->status ?? "draft")', ['type' => 'unknown', 'optional' => false]],
    'make(), a whenHas() payload' => ['\\'.EnumResource::class.'::make($this->whenHas("status"))', ['type' => 'StatusType', 'optional' => true, 'enumFqcn' => Status::class]],
    'new, a whenNotNull() payload' => ['new \\'.EnumResource::class.'($this->whenNotNull($this->visibility))', ['type' => 'VisibilityType', 'optional' => true, 'enumFqcn' => Visibility::class]],
]);

// The guard against a cyclic local is keyed by name, so it must not hide a parameter named like the local resolving,
// as in `$status = $this->whenHas('status', fn ($status) => EnumResource::make($status))`.
it('follows a closure parameter while an outer local of its name resolves', function () {
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->closureParamExprBindings['status'] = new PropertyFetch(new Variable('this'), 'status');
    $scope->resolvingLocalVars['status'] = true;
    $expr = new StaticCall(new Name(EnumResource::class), 'make', [new Arg(new Variable('status'))]);

    expect(new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class, 'toArray', null, $scope)->resolve($expr))
        ->toBe(['type' => 'StatusType', 'optional' => false, 'enumFqcn' => Status::class]);
});

it('follows a whenHas() parameter named like the local its value is assigned to', function () {
    $properties = new ResourceAstAnalyzer(new ReflectionClass(EnumResourceShadowedParameterResource::class), Post::class)->analyze()->properties;

    expect(array_map(fn (array $property): array => [$property['name'], $property['type'], $property['optional']], $properties))
        ->toBe([['status', 'StatusType', true]]);
});

// Only a collection wraps a list, and two enums that share a name are not one enum.
it('wraps a resolved payload only when it holds one enum and nothing else', function (string $subject, string $model, string $php, array $expected) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;

    expect(new ResourceAstAnalyzer(new ReflectionClass($subject), $model)->resolve($expr))->toBe($expected);
})->with([
    'collection(), a list' => [EnumCollectionResource::class, Team::class, '\\'.EnumResource::class.'::collection(collect($this->status_history)->all())', ['type' => 'StatusType[]', 'optional' => false, 'enumFqcn' => Status::class]],
    'make(), a list' => [EnumCollectionResource::class, Team::class, '\\'.EnumResource::class.'::make(collect($this->status_history)->all())', ['type' => 'unknown', 'optional' => false]],
    'make(), two enums that share a name' => [WarehouseResource::class, Warehouse::class, '\\'.EnumResource::class.'::make($this->status ?? $this->current_crm_status)', ['type' => 'unknown', 'optional' => false]],
]);

it('routes $this->resource::m() to analyzeStaticMethodOnResource() even inside a closure with a related model bound — guard 5 must precede guard 6', function () {
    // $this->resource::tableName() — guard 5 (`$this->resource::staticMethod()`) and guard 6
    // (closure-context `$this->relation::staticMethod()`) share the same PropertyFetch-receiver
    // shape; only guard 5's extra `name->toString() === 'resource'` check tells them apart. Setting
    // closureRelationModelClass to a model with no tableName() method makes a guard-6 misroute
    // observable: it would degrade to unknown instead of reflecting Post::tableName()'s `string`.
    $expr = new StaticCall(
        new PropertyFetch(new Variable('this'), 'resource'),
        'tableName',
    );
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->closureRelationModelClass = Address::class;

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'string',
        'optional' => false,
    ]);
});

it('keeps the foreign-receiver boundary: a non-self-returning method on a foreign resource class declines', function () {
    // new CategoryResource($this->parent)->summary() — CategoryResource::summary() declares `: array`,
    // not a self-returning type, and CategoryResource is not the subject under analysis
    // (FluentSelfResource), so this must decline rather than resolve against the wrong resource's
    // same-named method. See FluentSelfResource::foreign_summary in the workbench.
    $receiver = new New_(new Name(CategoryResource::class), [
        new Arg(new PropertyFetch(new Variable('this'), 'parent')),
    ]);
    $expr = new MethodCall($receiver, 'summary');
    $scope = new AnalysisScope(new ReflectionClass(FluentSelfResource::class));

    $engine = new StaticCallHandlerArmStubEngine([
        [$receiver, ['type' => 'CategoryResource', 'optional' => false, 'resourceFqcn' => CategoryResource::class]],
    ]);

    $result = (new StaticCallHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBeNull();
});

// A nested resource whose payload is null serializes as null, but resolve() runs the resource's own toArray() on it.
// PHP builds `new` and make() as an object, so a payload spelled that way never makes its wrapper null.
it('types a wrap\'s null arm: kept through a fluent self-returning call, dropped by resolve() and around a resource PHP builds', function (string $php, string $type) {
    expect(staticCallHandlerResolveOnCategory($php))->toBe($type);
})->with([
    'make() over a nullable relation' => ['self::make($this->parent)', 'FluentSelfResource | null'],
    'new over a nullable relation' => ['new self($this->parent)', 'FluentSelfResource | null'],
    'a self-returning method' => ['new self($this->parent)->markPreview()', 'FluentSelfResource | null'],
    'new around a make() that serializes as null' => ['new self(self::make($this->parent))', 'FluentSelfResource'],
    'new around a new that serializes as null' => ['new self(new self($this->parent))', 'FluentSelfResource'],
    'make() around a new that serializes as null' => ['self::make(new self($this->parent))', 'FluentSelfResource'],
    'make() around a whenLoaded() that returns null' => ['self::make($this->whenLoaded("parent", fn ($p) => new self($p)))', 'FluentSelfResource | null'],
    'resolve() on make()' => ['self::make($this->parent)->resolve()', 'FluentSelfResource'],
    'resolve() on new' => ['new self($this->parent)->resolve()', 'FluentSelfResource'],
    'collection() over a to-many' => ['self::collection($this->children)', 'FluentSelfResource[]'],
    'a nullsafe toResource()' => ['$this->parent?->toResource()', 'CategoryResource | null'],
    'a toResource() that throws on null' => ['$this->parent->toResource()', 'CategoryResource'],
]);

// The payload is resolved even when `new` decides the answer, so a warning from what it nests still reaches the run.
it('analyzes the expressions a new payload nests', function () {
    staticCallHandlerResolveOnCategory('new self(new self($this->when(true, fn ($x) => 1)))');

    expect(AnalysisWarnings::all())->toHaveCount(1);
});

// The arm is read from the type's top-level members, so a class name that spells "null" does not stand in for it.
it('adds the null arm of a ?static method once, and none for a static one', function (string $php, string $type) {
    expect(staticCallHandlerResolveOnCategory($php))->toBe($type);
})->with([
    'a class name that spells null' => ['\\'.AnnulledResource::class.'::make($this->resource)->maybe()', 'AnnulledResource | null'],
    'a receiver that already has the arm' => ['new self($this->parent)->whenAuthorized()', 'FluentSelfResource | null'],
    'a static return type' => ['new self($this->resource)->markPreview()', 'FluentSelfResource'],
]);

// collectResource() calls a method on its payload, so a resource collection, or the one ::collection() builds, throws
// on null instead of serializing as null.
it('adds no null arm to a resource collection built around a nullable payload', function (string $php, string $type) {
    expect(staticCallHandlerResolveOnCategory($php))->toBe($type);
})->with([
    'new on a collection' => ['new \StaticCallHandlerOrphanCollection($this->parent)', 'StaticCallHandlerOrphanCollection'],
    'make() on a collection' => ['\StaticCallHandlerOrphanCollection::make($this->parent)', 'StaticCallHandlerOrphanCollection'],
    'collection() on a resource' => ['self::collection($this->parent)', 'FluentSelfResource[]'],
]);

// Where a guard proves the payload's own read non-null, Laravel never serializes the wrap as null: a condition for the
// value or arm it runs, a filled transform() value for its callback, an early exit for the statements after it.
it('drops a wrap\'s null arm where a guard proves its payload non-null', function (string $php, string $type) {
    expect(staticCallHandlerResolveOnCategory($php))->toBe($type);
})->with([
    'when(), a truthy read' => ['$this->when($this->parent, fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), a value with no closure' => ['$this->when($this->parent, self::make($this->parent))', 'FluentSelfResource'],
    'when(), !== null' => ['$this->when($this->parent !== null, fn () => new self($this->parent))', 'FluentSelfResource'],
    'when(), null on the left' => ['$this->when(null !== $this->parent, fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), != null' => ['$this->when($this->parent != null, fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), ! is_null()' => ['$this->when(! is_null($this->parent), fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), isset()' => ['$this->when(isset($this->parent), fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), ! empty()' => ['$this->when(! empty($this->parent), fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), instanceof' => ['$this->when($this->parent instanceof \Workbench\App\Models\Category, fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), an && chain' => ['$this->when($this->relationLoaded("parent") && $this->parent, fn () => self::make($this->parent))', 'FluentSelfResource'],
    'when(), a negated || chain' => ['$this->when(! ($this->parent === null || $this->name === ""), fn () => self::make($this->parent))', 'FluentSelfResource'],
    'unless(), === null' => ['$this->unless($this->parent === null, fn () => self::make($this->parent))', 'FluentSelfResource'],
    'unless(), is_null()' => ['$this->unless(is_null($this->parent), fn () => self::make($this->parent))', 'FluentSelfResource'],
    'a ternary, a truthy read' => ['$this->parent ? self::make($this->parent) : "none"', 'FluentSelfResource | string'],
    'a ternary, !== null' => ['$this->parent !== null ? new self($this->parent) : "none"', 'FluentSelfResource | string'],
    'a ternary, isset()' => ['isset($this->parent) ? self::make($this->parent) : "none"', 'FluentSelfResource | string'],
    'a ternary, instanceof' => ['$this->parent instanceof \Workbench\App\Models\Category ? self::make($this->parent) : "none"', 'FluentSelfResource | string'],
    'a ternary, the else arm of === null' => ['$this->parent === null ? "none" : self::make($this->parent)', 'string | FluentSelfResource'],
    'a ternary, the else arm of a negated read' => ['! $this->parent ? "none" : self::make($this->parent)', 'string | FluentSelfResource'],
    'a ternary, the else arm of is_null()' => ['is_null($this->parent) ? "none" : self::make($this->parent)', 'string | FluentSelfResource'],
    'a ternary, the else arm of == null' => ['$this->parent == null ? "none" : self::make($this->parent)', 'string | FluentSelfResource'],
    'a ternary, the else arm of empty()' => ['empty($this->parent) ? "none" : self::make($this->parent)', 'string | FluentSelfResource'],
    'a ternary, a wrap its arm nests' => ['$this->parent ? ["p" => self::make($this->parent)] : "none"', '{ p: FluentSelfResource } | string'],
    'a ternary on a closure parameter' => ['$this->whenHas("parent", fn ($p) => $p ? self::make($p) : 1)', 'FluentSelfResource | number'],
    'mergeWhen(), a truthy read' => ['[$this->mergeWhen($this->parent, ["p" => self::make($this->parent)])]', '{ p?: FluentSelfResource }'],
    'mergeUnless(), === null' => ['[$this->mergeUnless($this->parent === null, fn () => ["p" => self::make($this->parent)])]', '{ p?: FluentSelfResource }'],
    'transform(), its parameter' => ['$this->transform($this->parent, fn ($p) => self::make($p))', 'FluentSelfResource'],
    'transform(), the read it passes' => ['$this->transform($this->parent, fn ($p) => self::make($this->parent))', 'FluentSelfResource'],
    'an early exit in a closure' => ['$this->when(true, function () { if ($this->parent === null) { return "none"; } return self::make($this->parent); })', 'string | FluentSelfResource'],
]);

// A guard proves only the read it tests, only where it holds, and only until a closure parameter takes the read's name.
it('keeps a wrap\'s null arm where no guard proves its payload non-null', function (string $php, string $type) {
    expect(staticCallHandlerResolveOnCategory($php))->toBe($type);
})->with([
    'when(), a foreign key, since a soft-deleted parent loads as null' => ['$this->when($this->parent_id !== null, fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), another read' => ['$this->when($this->name, fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), === null' => ['$this->when($this->parent === null, fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), an || chain' => ['$this->when($this->parent || $this->name, fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), a negated && chain' => ['$this->when(! ($this->parent && $this->name), fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), is_null()' => ['$this->when(is_null($this->parent), fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), another function' => ['$this->when(! is_array($this->parent), fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), !== another value' => ['$this->when($this->parent !== false, fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'when(), its default' => ['$this->when($this->parent, 1, fn () => self::make($this->parent))', 'number | FluentSelfResource | null'],
    'unless(), a truthy read' => ['$this->unless($this->parent, fn () => self::make($this->parent))', 'FluentSelfResource | null'],
    'a ternary, the else arm of a truthy read' => ['$this->parent ? "none" : self::make($this->parent)', 'string | FluentSelfResource | null'],
    'a ternary, the else arm of !== null' => ['$this->parent !== null ? "none" : self::make($this->parent)', 'string | FluentSelfResource | null'],
    'a ternary, the else arm of isset()' => ['isset($this->parent) ? "none" : self::make($this->parent)', 'string | FluentSelfResource | null'],
    'a ternary, the else arm of instanceof' => ['$this->parent instanceof \Workbench\App\Models\Category ? "none" : self::make($this->parent)', 'string | FluentSelfResource | null'],
    'a ternary, the if arm of empty()' => ['empty($this->parent) ? self::make($this->parent) : "none"', 'FluentSelfResource | string | null'],
    'a ternary, a comparison' => ['$this->id > 0 ? self::make($this->parent) : "none"', 'FluentSelfResource | string | null'],
    'a key beside a ternary' => ['["a" => $this->parent ? 1 : 2, "b" => self::make($this->parent)]', '{ a: number; b: FluentSelfResource | null }'],
    'a key beside a when()' => ['["a" => $this->when($this->parent, 1), "b" => self::make($this->parent)]', '{ a?: number; b: FluentSelfResource | null }'],
    'mergeUnless(), a truthy read' => ['[$this->mergeUnless($this->parent, ["p" => self::make($this->parent)])]', '{ p?: FluentSelfResource | null }'],
    'transform(), its default' => ['$this->transform($this->parent, fn ($p) => 1, fn ($p) => self::make($p))', 'number | FluentSelfResource | null'],
    'a closure body that writes the guarded read' => ['$this->when($this->parent, function () { $this->parent = null; return self::make($this->parent); })', 'FluentSelfResource | null'],
    'a closure parameter named like the guarded read' => ['$this->whenHas("parent", fn ($p) => $this->when($p, fn ($p = null) => self::make($p)))', 'FluentSelfResource | null'],
    'a return inside an early exit' => ['$this->when(true, function () { if ($this->parent === null) { return self::make($this->parent); } return "none"; })', 'FluentSelfResource | string | null'],
    'an early exit the body writes past' => ['$this->when(true, function () { if ($this->parent === null) { return "none"; } $this->parent = null; return self::make($this->parent); })', 'string | FluentSelfResource | null'],
]);

it('drops a wrap\'s null arm after a method\'s early exit proves its payload non-null', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(StaticCallHandlerEarlyExitResource::class), Category::class)->analyze()->properties)->keyBy('name');

    expect($props['inside']['type'])->toBe('CategoryResource | null')
        ->and($props['spread']['type'])->toBe('CategoryResource | null')
        ->and($props['after']['type'])->toBe('CategoryResource')
        ->and($props['guarded']['type'])->toBe('{ spread: CategoryResource }');
});

it('declines an expression it does not claim', function () {
    $expr = new MethodCall(new Variable('this'), 'somethingElse');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBeNull();
});

test('new SomeResource(...)->resolve() strips resolve() like the static form', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['author_resource']['type'])->toBe('UserResource')
        ->and($props['author_resource']['optional'])->toBeTrue();
});

// NewResourceHandler

it('resolves new PostResource(...) to the resource channel', function () {
    $payload = new PropertyFetch(new Variable('this'), 'post');
    $expr = new New_(new Name(PostResource::class), [new Arg($payload)]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $engine = new StaticCallHandlerArmStubEngine([[$payload, ['type' => 'Post', 'optional' => false]]]);

    $result = (new NewResourceHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBe([
        'type' => 'PostResource',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
    ]);
});

// Inside a resource's toArray(), filter() serializes a nested resource whose payload is null as null.
it('adds the null arm a nullable payload gives a nested resource, only inside a resource', function (string $subject, string $type) {
    $payload = new PropertyFetch(new Variable('this'), 'post');
    $engine = new StaticCallHandlerArmStubEngine([[$payload, ['type' => 'Post | null', 'optional' => false]]]);
    $scope = new AnalysisScope(new ReflectionClass($subject));

    expect((new NewResourceHandler)->resolve(new New_(new Name(PostResource::class), [new Arg($payload)]), $scope, $engine)['type'] ?? null)
        ->toBe($type)
        ->and((new StaticCallHandler)->resolve(new StaticCall(new Name(PostResource::class), 'make', [new Arg($payload)]), $scope, $engine)['type'] ?? null)
        ->toBe($type);
})->with([
    'a resource subject' => [PostResource::class, 'PostResource | null'],
    'a controller subject, whose payload Inertia never nulls' => [InertiaSingleResourceController::class, 'PostResource'],
]);

// A resource's static make() builds an object like `new`; a call to any other class or method can return null.
it('drops the null arm of a make() payload only when a resource built it', function (Expr $payload, array $made, string $type) {
    $engine = new StaticCallHandlerArmStubEngine([[$payload, $made]]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    expect((new NewResourceHandler)->resolve(new New_(new Name(PostResource::class), [new Arg($payload)]), $scope, $engine)['type'] ?? null)
        ->toBe($type)
        ->and((new StaticCallHandler)->resolve(new StaticCall(new Name(PostResource::class), 'make', [new Arg($payload)]), $scope, $engine)['type'] ?? null)
        ->toBe($type);
})->with([
    'a resource built it' => [new StaticCall(new Name(PostResource::class), 'make'), ['type' => 'PostResource | null', 'optional' => false, 'resourceFqcn' => PostResource::class], 'PostResource'],
    'another class returned it' => [new StaticCall(new Name(Post::class), 'make'), ['type' => 'Post | null', 'optional' => false], 'PostResource | null'],
    'an expression names the method' => [new StaticCall(new Name(PostResource::class), new Variable('method')), ['type' => 'PostResource | null', 'optional' => false, 'resourceFqcn' => PostResource::class], 'PostResource | null'],
    'an instance method is no static make()' => [new MethodCall(new Variable('factory'), 'make'), ['type' => 'PostResource | null', 'optional' => false, 'resourceFqcn' => PostResource::class], 'PostResource | null'],
]);

it('declines a node outside its claimed New_ class', function () {
    // NewResourceHandler only claims New_::class, and analyzeNewResource() always resolves to a
    // concrete result (down to the `unknown` floor) for any New_ node it is actually given — so the
    // only reachable decline is the top-level class guard itself, exercised here directly.
    $expr = new String_('not a new expression');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new NewResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// Resource payloads written by name — `resource:` is JsonResource::__construct()'s and ::collection()'s
// parameter, and make() forwards it there through `new static(...$parameters)`.

it('resolves PostResource::collection(resource: $this->whenLoaded(…)) as optional through the named payload', function () {
    $expr = new StaticCall(new Name(PostResource::class), 'collection', [
        new Arg(new MethodCall(new Variable('this'), 'whenLoaded', [new Arg(new String_('posts'))]), name: new Identifier('resource')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'PostResource[]',
        'optional' => true,
        'resourceFqcn' => PostResource::class,
    ]);
});

it('resolves EnumResource::make(resource: $this->status) to the enum channel', function () {
    $expr = new StaticCall(new Name(EnumResource::class), 'make', [
        new Arg(new PropertyFetch(new Variable('this'), 'status'), name: new Identifier('resource')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'StatusType',
        'optional' => false,
        'enumFqcn' => Status::class,
    ]);
});

it('resolves new PostResource(resource: $this->when(…)) as optional through the named payload', function () {
    $payload = new MethodCall(new Variable('this'), 'when', [new Arg(new Variable('flag')), new Arg(new Variable('post'))]);
    $expr = new New_(new Name(PostResource::class), [new Arg($payload, name: new Identifier('resource'))]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $engine = new StaticCallHandlerArmStubEngine([[$payload, ['type' => 'Post', 'optional' => true]]]);

    $result = (new NewResourceHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBe([
        'type' => 'PostResource',
        'optional' => true,
        'resourceFqcn' => PostResource::class,
    ]);
});

it('resolves new NamedPayloadResource(payload: $this->whenLoaded(…)) through the concrete constructor, not JsonResource\'s', function () {
    $payload = new MethodCall(new Variable('this'), 'whenLoaded', [new Arg(new String_('x'))]);
    $expr = new New_(new Name(NamedPayloadResource::class), [new Arg($payload, name: new Identifier('payload'))]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));
    $engine = new StaticCallHandlerArmStubEngine([[$payload, ['type' => 'unknown', 'optional' => true]]]);

    $result = (new NewResourceHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBe([
        'type' => 'NamedPayloadResource',
        'optional' => true,
        'resourceFqcn' => NamedPayloadResource::class,
    ]);
});

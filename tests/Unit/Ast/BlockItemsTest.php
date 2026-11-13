<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallableForms;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Bodies\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators\OffsetReadHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CommentsGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldCallableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAbilityGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAliasCallGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAliasOfAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAliasPassGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableArrayAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAuthorizedGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAuthorWhenLoadedCallableGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAuthorWhenLoadedGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAuthorWhenLoadedPassGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableAuthorWhenLoadedReadGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableBlockGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableBranchGuardsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableClosureRequestGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableCoalesceAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableEarlyExtractGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableEarlyGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableEvalGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableExtractRequestGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableForeachAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableIncludeGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableProxyAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableProxyPassGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableReassignedRequestGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableReferenceAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableRewriteGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableSafeCallsGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableThisAliasGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableThisPassGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableUseRequestGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableVariableVariableGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ImageableWhenLoadedKeyGuardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MemberNarrowedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NarrowedRelationPathResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\OffsetReadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PregMatchResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProvenBlockHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProvenHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RewrittenPageCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\RewrittenThisHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyCollectedPost;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A post titled Banana, as the fixtures here read it.
 */
function blockItemsPost(): Post
{
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);

    return $post;
}

/**
 * What heldCallable() answers for each `$c` a `$this->when()` call passes, in source order: whether it reads the read
 * as the first-class callable the variable holds there.
 *
 * @param  array<string, class-string>  $parameters  each parameter's model, bound before the body is seeded
 * @return list<bool>
 */
function blockItemsHeldCallables(string $body, array $parameters = []): array
{
    $scope = new AnalysisScope(new ReflectionClass(HeldCallableResource::class), Post::class);
    $scope->varModelBindings = $parameters;
    /** @var list<Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsLocalVarBindings;

        /**
         * Seed the scope from the body, as every body reader does.
         *
         * @param  list<Stmt>  $stmts
         */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->seedBody($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);
    $calls = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof MethodCall
        && $node->name instanceof Identifier
        && $node->name->toString() === 'when');

    return array_map(function (Node $call) use ($scope): bool {
        $read = $call instanceof MethodCall ? $call->getArgs()[1]->value : null;

        return $read !== null && CallableForms::heldCallable($read, $scope) !== $read;
    }, $calls);
}

/**
 * What OffsetReadHandler answers for an offset read of a holder of the given type, `?` marking an optional read.
 */
function blockItemsOffsetRead(string $holder, string $read): ?string
{
    $engine = new class($holder) implements ExpressionEngine
    {
        /** The holder's type, answered for whatever is resolved. */
        public function __construct(private readonly string $holder) {}

        /** Types every expression as the holder. */
        public function resolve(Expr $expr): array
        {
            return ['type' => $this->holder, 'optional' => false];
        }

        /** Spreads nothing. */
        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            return null;
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
        }

        /** Fails the test: no array is analyzed here. */
        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called here');
        }
    };
    $expr = new AstParser()->parseSource('<?php '.$read.';')[0]->expr;
    assert($expr instanceof ArrayDimFetch);

    $read = new OffsetReadHandler()->resolve($expr, new AnalysisScope(new ReflectionClass(OffsetReadResource::class)), $engine);

    return $read === null ? null : ($read['optional'] ? '?' : '').$read['type'];
}

test('a first-class callable held in a local reads as its call where Laravel calls the value, and warns of nothing', function () {
    AnalysisWarnings::reset();
    $loaded = blockItemsPost();
    $author = new User;
    $author->forceFill(['name' => 'Ann']);
    $loaded->setRelation('author', $author);

    expect(publishedResourceKeyTypes(HeldCallableResource::class, Post::class))->toBe([
        'label?' => 'string',
        'fallback' => 'number | string',
        'not_null' => 'string',
        'author?' => 'string',
        'shouted?' => 'string',
        'has_title?' => 'string',
        'held?' => 'number',
        'merged_default?' => 'string',
        'merged?' => 'boolean',
    ])
        ->and(AnalysisWarnings::all())->toBe([])
        ->and(json_encode(new HeldCallableResource($loaded)->resolve(Request::create('/', 'GET', ['label' => 1, 'held' => 1, 'plain' => 1, 'merged' => 1]))))
        ->toBe('{"label":"BANANA","fallback":"BANANA","not_null":"BANANA","author":"Ann","shouted":"BANANA","has_title":"BANANA","held":1,"merged_default":"x","merged":true}')
        ->and(json_encode(new HeldCallableResource(blockItemsPost())->resolve(Request::create('/', 'GET', ['none' => 1]))))
        ->toBe('{"fallback":1,"not_null":"BANANA","shouted":"BANANA","has_title":"BANANA"}');
});

test('heldCallable() reads the first-class callable a local holds where it is read, and leaves any other read as written', function (string $body, array $parameters, array $held) {
    expect(blockItemsHeldCallables($body, $parameters))->toBe($held);
})->with([
    'a top-level write' => ['$c = $this->label(...); $this->when(true, $c);', [], [true]],
    'a local written twice, read past its second write' => ['$c = $this->label(...); $c = $this->upper(...); $this->when(true, $c);', [], [true]],
    'a local a branch may write again' => ['$c = $this->label(...); if ($x) { $c = $this->upper(...); } $this->when(true, $c);', [], [false]],
    'a call, which holds its result' => ['$c = $this->label(); $this->when(true, $c);', [], [false]],
    'a block write, read in its block and past it' => [
        'if ($x) { $c = $this->label(...); $this->when(true, $c); } $this->when(true, $c);',
        [],
        [true, false],
    ],
    'a write an inline @var declares' => ['/** @var \Closure $c */ $c = $this->label(...); $this->when(true, $c);', [], [true]],
    'a parameter read before the write' => ['$this->when(true, $c); $c = $this->label(...);', ['c' => User::class], [false]],
]);

// A when() slot holds a MissingValue, which `??` passes through and the response leaves out, so it never sends null.
test('a literal-key read types the member of the shape its holder binds, and a key the shape lacks declines', function () {
    expect(publishedResourceKeyTypes(OffsetReadResource::class, Post::class))->toBe([
        'param' => '{ a: number | null }',
        'local' => 'string',
        'mapped' => 'number[]',
        'missing' => 'null',
        'tuple' => 'string',
        'slot?' => 'number',
        'slot_or_none?' => 'number | string',
    ])
        ->and(json_encode(new OffsetReadResource(blockItemsPost())->resolve(Request::create('/'))))
        ->toBe('{"param":{"a":1},"local":"x","mapped":[1,2],"missing":null,"tuple":"Banana"}')
        ->and(json_encode(new OffsetReadResource(blockItemsPost())->resolve(Request::create('/', 'GET', ['a' => 1]))))
        ->toContain('"tuple":"Banana","slot":1,"slot_or_none":1}');
});

// A list, a Record and an index signature name no key the array is sure to hold, so their reads decline.
test('an offset read takes the member each arm of its holder names, and declines a holder or key it cannot be sure of', function (string $holder, string $read, ?string $type) {
    expect(blockItemsOffsetRead($holder, $read))->toBe($type);
})->with([
    'an object member' => ['{ a: number; b: string }', '$x["b"]', 'string'],
    'an optional member' => ['{ a?: number }', '$x["a"]', '?number'],
    'an optional member beside a null arm' => ['{ a?: number } | null', '$x["a"]', '?number | null'],
    'a nullable member' => ['{ a: string | null }', '$x["a"]', 'string | null'],
    'a quoted key' => ["{ 'a-b': number }", '$x["a-b"]', 'number'],
    'a key whose slash json_encode() escapes' => ['{ "a\\/b": number }', '$x["a/b"]', 'number'],
    'a key json_encode() writes as a unicode escape' => ['{ "\\u00e9"?: number }', '$x["é"]', '?number'],
    'a key holding an escaped quote' => ["{ 'it\\'s': number }", '$x["it\'s"]', 'number'],
    'an int key of an object' => ['{ 0: string; 2: boolean }', '$x[2]', 'boolean'],
    'a null arm' => ['{ a: number } | null', '$x["a"]', 'number | null'],
    'two object arms' => ['{ a: number } | { a: string }', '$x["a"]', 'number | string'],
    'a tuple element' => ['[number, string]', '$x[1]', 'string'],
    'a tuple element by a numeric string' => ['[number, string]', '$x["0"]', 'number'],
    'an optional tuple element' => ['[number, string?]', '$x[1]', '?string'],
    'an optional tuple element of a union' => ['[number, (string | boolean)?]', '$x[1]', '?string | boolean'],
    'a list' => ['string[]', '$x[0]', null],
    'a Record' => ['Record<string, number>', '$x["a"]', null],
    'an index signature' => ['{ [key: string]: number }', '$x["a"]', null],
    'a key one arm lacks' => ['{ a: number } | { b: string }', '$x["a"]', null],
    'a tuple index past its end' => ['[number]', '$x[1]', null],
    'a tuple index with a leading zero, which PHP keeps a string' => ['[number, string]', '$x["01"]', null],
    'a tuple index past PHP_INT_MAX' => ['[number, string]', '$x["9223372036854775808"]', null],
    'a rest element' => ['[number, ...string[]]', '$x[1]', null],
    'an unknown member' => ['{ a: unknown }', '$x["a"]', null],
    'a member naming a class' => ['{ a: Post }', '$x["a"]', null],
    'a computed key' => ['{ a: number }', '$x[$k]', null],
    'a holder that is only null' => ['null', '$x["a"]', null],
    'an empty array arm, which holds no key' => ['{ a: number } | never[]', '$x["a"]', 'number'],
    'an empty tuple arm' => ['{ a?: number } | []', '$x["a"]', '?number'],
    'a holder that is only an empty array' => ['never[] | null', '$x["a"]', null],
    'a string' => ['string', '$x["a"]', null],
]);

// A named group keys the groups and a flag nests them. A call that may not run leaves what the name held, so it writes
// only over a name nothing else writes, or a value its matches stand for.
test('preg_match() writes its matches, but not over a value a skipped call leaves', function () {
    expect(publishedResourceKeyTypes(PregMatchResource::class, Post::class))->toBe([
        'm' => 'string[]',
        'bare' => 'string[]',
        'named' => '{ "0": string; first?: string; "1"?: string } | never[]',
        'offsets' => '[string, number][]',
        'kept' => 'unknown',
        'maybe' => 'string[]',
        'decoded' => 'unknown',
        'flagged' => 'unknown',
        'else_null' => 'unknown',
        'else_number' => 'unknown',
        'list' => 'unknown',
        'parameter' => 'unknown',
        'listed_parameter' => 'string[][]',
        'run_parameter' => 'string[][]',
    ])
        ->and(json_encode(new PregMatchResource(blockItemsPost())->resolve(Request::create('/'))))
        ->toBe('{"m":["Ba","B","a"],"bare":["Ba","B","a"],"named":{"0":"B","first":"B","1":"B"},"offsets":[["B",0],["B",0]],'
            .'"kept":"none","maybe":[],"decoded":{"x":1},"flagged":[["B",0],["B",0]],"else_null":null,"else_number":5,'
            .'"list":[1],"parameter":["ab"],"listed_parameter":[["x"]],"run_parameter":[["a","a"]]}')
        ->and(json_encode(new PregMatchResource(blockItemsPost())->resolve(Request::create('/', 'GET', ['match' => 1]))))
        ->toContain('"kept":["B","B"],"maybe":["B","B"],"decoded":["B","B"],"flagged":["B","B"],"else_null":["B","B"],'
            .'"else_number":["B","B"],"list":["B","B"],"parameter":[["a","a"]],"listed_parameter":[["a","a"]]');
});

// The arm reads the same property the test read, so it holds the tested class; a read past the ternary keeps them all.
test('an instanceof ternary on a $this->prop narrows the reads through it', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());
    $product = new Product;
    $product->forceFill(['id' => 'p-1']);
    $productImage = new Image;
    $productImage->setRelation('imageable', $product);

    expect(publishedResourceKeyTypes(MemberNarrowedResource::class, Image::class))->toBe([
        'title' => 'string | null',
        'key' => 'number | null',
        'either_key' => 'number | null',
        'open_key' => 'number | string | null',
    ])
        ->and(json_encode(new MemberNarrowedResource($image)->resolve(Request::create('/'))))
        ->toBe('{"title":"Banana","key":7,"either_key":7,"open_key":7}')
        ->and(json_encode(new MemberNarrowedResource($productImage)->resolve(Request::create('/'))))
        ->toBe('{"title":null,"key":null,"either_key":null,"open_key":"p-1"}');
});

// An `if` proves the chain it tests as a ternary does, in each block it runs and past a guard that always leaves.
it('narrows a $this->prop path inside an if block, its else and past an early return', function (string $resource, array $types, array $sent) {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'Ada']);
    $sends = function (Model $imageable) use ($resource): string {
        $image = new Image;
        $image->setRelation('imageable', $imageable);

        return (string) json_encode(new $resource($image)->resolve(Request::create('/')));
    };

    expect(publishedResourceKeyTypes($resource, Image::class))->toBe($types)
        ->and([$sends(blockItemsPost()), $sends($user)])->toBe($sent);
})->with([
    'a block that returns' => [ImageableBlockGuardResource::class, ['t' => 'string | null', 'id' => 'number | null', 'kind' => 'string'], [
        '{"t":"Banana","id":7,"kind":"post"}',
        '{"t":null,"id":null,"kind":"other"}',
    ]],
    'past a guard that returns' => [ImageableEarlyGuardResource::class, ['t' => 'string | null'], ['{"t":"Banana"}', '{"t":null}']],
    'blocks that assign, an else, $this->resource and a ternary inside' => [ImageableBranchGuardsResource::class, [
        'assigned' => 'string | null',
        'branched' => 'string | null',
        'proxied' => 'string | null',
        'nested' => 'string | null',
    ], [
        '{"assigned":"Banana","branched":"Banana","proxied":"Banana","nested":"Banana"}',
        '{"assigned":null,"branched":null,"proxied":null,"nested":null}',
    ]],
]);

// The write replaces what the test saw, so no read in the block holds the tested class.
it('narrows no read of a $this->prop path in an if block that replaces it', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());
    $image->setRelation('reviewable', null);

    expect(publishedResourceKeyTypes(ImageableRewriteGuardResource::class, Image::class))->toBe(['t' => 'unknown'])
        ->and(json_encode(new ImageableRewriteGuardResource($image)->resolve(Request::create('/'))))->toBe('{"t":null}');
});

// A callee given `$this` or `$this->resource`, or a closure made before the test, may swap the morph, so all may stand.
it('narrows no read of a $this->prop path past a call that may replace what it reads through', function (string $resource) {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $product = new Product;
    $product->forceFill(['id' => 'p-1']);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());
    $image->setRelation('reviewable', $product);

    expect(publishedResourceKeyTypes($resource, Image::class))->toBe(['k' => 'number | string | null'])
        ->and(json_encode(new $resource($image)->resolve(Request::create('/'))))->toBe('{"k":"p-1"}');
})->with([
    '$this->resource passed to a helper' => [ImageableProxyPassGuardResource::class],
    '$this passed to a helper' => [ImageableThisPassGuardResource::class],
    'a closure made before the test, called in its block' => [ImageableAliasCallGuardResource::class],
    'a closure made before the test, passed to when() in its block' => [ImageableAliasPassGuardResource::class],
    '$this->resource passed to a can() that is no Authorizable' => [ImageableAbilityGuardResource::class],
    'a local holding $this->resource, called on' => [ImageableProxyAliasGuardResource::class],
    'a local holding $this, passed to a helper' => [ImageableThisAliasGuardResource::class],
    'a closure parameter $request that is no Request, its user()\'s can() given $this->resource' => [ImageableClosureRequestGuardResource::class],
    'the $request parameter replaced before the test' => [ImageableReassignedRequestGuardResource::class],
    'an element of an array holding $this->resource, called on' => [ImageableArrayAliasGuardResource::class],
    'a local copied from one holding $this->resource, passed to a helper' => [ImageableAliasOfAliasGuardResource::class],
    'a local set to $this->resource ?? null, called on' => [ImageableCoalesceAliasGuardResource::class],
    'a foreach value over [$this->resource], called on' => [ImageableForeachAliasGuardResource::class],
    'eval() code swapping it' => [ImageableEvalGuardResource::class],
    'an included file swapping it' => [ImageableIncludeGuardResource::class],
    'an extract()ed $request, its user()\'s can() given $this->resource' => [ImageableExtractRequestGuardResource::class],
    'a $request extract()ed before the test, its user()\'s can() given $this->resource' => [ImageableEarlyExtractGuardResource::class],
    'a $request a variable variable names, its user()\'s can() given $this->resource' => [ImageableVariableVariableGuardResource::class],
]);

// A local bound to the chain by reference writes the chain whenever it is written.
it('narrows no read of a declared $this->prop past a write to a local bound to it by reference', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $product = new Product;
    $product->forceFill(['id' => 'p-1']);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());
    $image->setRelation('reviewable', $product);

    expect(publishedResourceKeyTypes(ImageableReferenceAliasGuardResource::class, Image::class))->toBe(['k' => 'unknown'])
        ->and(json_encode(new ImageableReferenceAliasGuardResource($image)->resolve(Request::create('/'))))->toBe('{"k":"p-1"}');
});

// A whenLoaded() callback is handed the loaded member, so what it does to that member follows the rules for the member
// itself, and a callable that is no closure written there may do anything with it.
it('narrows a nested $this->prop path past a whenLoaded() callback only where it cannot reach what the path reads through', function (string $resource, array $types, string $sent) {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $author = new User;
    $author->forceFill(['id' => 2, 'name' => 'Bea']);
    $post = blockItemsPost();
    $post->setRelation('author', $author);
    $image = new Image;
    $image->setRelation('imageable', $post);

    expect(publishedResourceKeyTypes($resource, Image::class))->toBe($types)
        ->and(json_encode(new $resource($image)->resolve(Request::create('/'))))->toBe($sent);
})->with([
    'a callback setting the relation on the member' => [ImageableAuthorWhenLoadedGuardResource::class, ['k' => 'unknown', 'n' => 'unknown'], '{"k":"p-1","n":null}'],
    'a callback passing the member to a helper' => [ImageableAuthorWhenLoadedPassGuardResource::class, ['k' => 'unknown', 'n' => 'unknown'], '{"k":"p-1","n":null}'],
    'a first-class callable' => [ImageableAuthorWhenLoadedCallableGuardResource::class, ['k' => 'unknown', 'n' => 'unknown'], '{"k":"p-1","n":null}'],
    'a callback reading the path off the member' => [ImageableAuthorWhenLoadedReadGuardResource::class, ['k' => 'number | null', 'n' => 'string | null'], '{"k":2,"n":"Bea"}'],
]);

// A whenLoaded() callback handed the tested chain itself may call on it, as the block may.
it('narrows a $this->prop path past a whenLoaded() callback calling on the member it tests', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());

    expect(publishedResourceKeyTypes(ImageableWhenLoadedKeyGuardResource::class, Image::class))->toBe(['loaded?' => 'number | string | null', 'k' => 'number | null'])
        ->and(json_encode(new ImageableWhenLoadedKeyGuardResource($image)->resolve(Request::create('/'))))->toBe('{"loaded":7,"k":7}');
});

// A static helper given the chain's own member, and when() given a closure reading it, cannot reach the chain.
it('narrows a $this->prop path past calls that cannot reach it', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'Ada']);
    $sends = function (Model $imageable): string {
        $image = new Image;
        $image->setRelation('imageable', $imageable);

        return (string) json_encode(new ImageableSafeCallsGuardResource($image)->resolve(Request::create('/')));
    };

    expect(publishedResourceKeyTypes(ImageableSafeCallsGuardResource::class, Image::class))->toBe(['t' => 'string | null', 'w?' => 'string | null'])
        ->and([$sends(blockItemsPost()), $sends($user)])->toBe(['{"t":"Banana","w":"Banana"}', '{"t":null,"w":null}']);
});

// An authorization check only reads the model it is given, so the proof holds past it.
it('narrows a $this->prop path past an authorization check given $this->resource', function (string $resource) {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());

    expect(publishedResourceKeyTypes($resource, Image::class))->toBe(['k' => 'number | null'])
        ->and(json_encode(new $resource($image)->resolve(Request::create('/'))))->toBe('{"k":7}');
})->with([
    'on the method\'s own request parameter' => [ImageableAuthorizedGuardResource::class],
    'inside a closure importing that parameter by value' => [ImageableUseRequestGuardResource::class],
]);

// Only the test names the chain's class: a declared return still reads its type, a conditional guess does not.
it('withdraws a conditional guess, not a declared return, off a $this->prop path an if proves a collection', function () {
    $comment = new Comment;
    $comment->forceFill(['id' => 5]);
    $post = blockItemsPost();
    $post->setRelation('comments', new EloquentCollection([$comment]));

    expect(resourceProps(CommentsGuardResource::class))->toBe(['random' => 'unknown', 'count' => 'number'])
        ->and(json_encode(new CommentsGuardResource($post)->resolve(Request::create('/'))))->toBe('{"random":{"id":5},"count":1}');
});

// A helper read in the arm reads the same object, so the proof holds in its body, unless that writes through `$this`.
test('a spread helper keeps an instanceof proof on a $this->prop unless its body writes through $this', function () {
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);
    $image = new Image;
    $image->setRelation('imageable', blockItemsPost());

    expect(publishedResourceKeyTypes(RewrittenThisHelperResource::class, Image::class))->toBe([
        'kept' => '{ key: number } | null',
        'rewritten' => '{ key: number | string } | null',
    ])
        ->and(json_encode(new RewrittenThisHelperResource($image)->resolve(Request::create('/'))))
        ->toBe('{"kept":{"key":7},"rewritten":{"key":"p-1"}}');
});

// Only a write that replaces `$this->resource` whole can change what the caller's test saw on it.
test('a spread helper keeps an instanceof proof on the items unless its body replaces $this->resource', function () {
    $post = new ProxyCollectedPost;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);
    $sent = json_encode(new RewrittenPageCollection(new LengthAwarePaginator([$post], 1, 15))->resolve(Request::create('/')));
    $items = 'ProxyCollectedPostResource[] | LengthAwarePaginator<ProxyCollectedPostResource> | '
        .'SimplePaginator<ProxyCollectedPostResource> | CursorPaginator<ProxyCollectedPostResource>';

    expect(publishedResourceKeyTypes(RewrittenPageCollection::class, ProxyCollectedPost::class))->toBe([
        'marked' => '{ pager: LengthAwarePaginator<ProxyCollectedPostResource> } | null',
        'replaced' => '{ pager: '.$items.' } | null',
    ])
        ->and($sent)->toContain('"marked":{"pager":{"current_page":1,"data":[{"id":7,"title":"Banana"}]')
        ->and($sent)->toContain('"replaced":{"pager":[{"id":7,"title":"Banana"}]}')
        ->and(json_encode(new RewrittenPageCollection(collect([$post]))->resolve(Request::create('/'))))
        ->toBe('{"marked":null,"replaced":null}');
});

// The proof replaces only the classes, so a relation collection keeps its element model, and its first() the model.
test('an instanceof ternary on a $this->prop keeps what the narrowed collection holds', function () {
    $image = new Image;
    $image->forceFill(['id' => 3]);
    $comment = new Comment;
    $comment->forceFill(['id' => 4]);
    $author = new User;
    $author->setRelation('posts', new EloquentCollection([blockItemsPost()]));
    $post = blockItemsPost();
    $post->setRelation('images', new EloquentCollection([$image]));
    $post->setRelation('comments', new EloquentCollection([$comment]));
    $post->setRelation('author', $author);
    $empty = blockItemsPost();
    $empty->setRelation('images', new EloquentCollection);
    $empty->setRelation('comments', new EloquentCollection);
    $empty->setRelation('author', null);

    expect(publishedResourceKeyTypes(NarrowedRelationPathResource::class, Post::class))->toBe([
        'image_id' => 'number | null',
        'comment_id' => 'number | null',
        'author_post_title' => 'string | null',
    ])
        ->and(json_encode(new NarrowedRelationPathResource($post)->resolve(Request::create('/'))))
        ->toBe('{"image_id":3,"comment_id":4,"author_post_title":"Banana"}')
        ->and(json_encode(new NarrowedRelationPathResource($empty)->resolve(Request::create('/'))))
        ->toBe('{"image_id":null,"comment_id":null,"author_post_title":null}');
});

// The helper's body reads `$this->resource` as the class the caller's test proves, a relation only that subclass has.
test('a helper read as a value under the caller\'s instanceof proof reads the proven model', function () {
    $owner = new User;
    $owner->forceFill(['name' => 'Ann']);
    $subscribed = new SubscribedTeam;
    $subscribed->forceFill(['name' => 'T']);
    $subscribed->setRelation('subscriber', $owner);
    $team = new Team;
    $team->forceFill(['name' => 'U']);

    expect(publishedResourceKeyTypes(ProvenHelperResource::class, Team::class))->toBe([
        'proven' => 'string | null',
        'unproven' => 'unknown',
        'name' => 'string',
    ])
        ->and(json_encode(new ProvenHelperResource($subscribed)->resolve(Request::create('/'))))
        ->toBe('{"proven":"Ann","unproven":"Ann","name":"T"}')
        ->and(json_encode(new ProvenHelperResource($team)->resolve(Request::create('/'))))
        ->toBe('{"proven":null,"unproven":null,"name":"U"}')
        ->and(publishedResourceKeyTypes(ProvenBlockHelperResource::class, Team::class))->toBe(['subscriber' => 'string | null'])
        ->and(json_encode(new ProvenBlockHelperResource($subscribed)->resolve(Request::create('/'))))->toBe('{"subscriber":"Ann"}')
        ->and(json_encode(new ProvenBlockHelperResource($team)->resolve(Request::create('/'))))->toBe('{"subscriber":null}');
});

// The backing joins the memo key, so a read under a proof and one without each keep their own answer, in either order.
test('a helper read under a proof and the same helper read without it never share a memoized answer', function () {
    $read = fn (?string $backing): ?string => resolve(MethodReturnTypeResolver::class)
        ->resolve(ProvenHelperResource::class, 'subscriberName', backing: $backing)['type'] ?? null;

    resolve(AnalysisMemo::class)->reset();
    $provenFirst = [$read(SubscribedTeam::class), $read(null)];
    resolve(AnalysisMemo::class)->reset();
    $unprovenFirst = [$read(null), $read(SubscribedTeam::class)];

    expect($provenFirst)->toBe(['string | null', null])
        ->and($unprovenFirst)->toBe([null, 'string | null']);
});

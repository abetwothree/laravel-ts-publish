<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\OffsetReadHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HeldCallableResource;
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

        return $read !== null && FirstClassCallableHandler::heldCallable($read, $scope) !== $read;
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
        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            return null;
        }

        /** Calls no callable it reads. */
        public function invokesCallables(): bool
        {
            return false;
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
    'a local written twice' => ['$c = $this->label(...); $c = $this->upper(...); $this->when(true, $c);', [], [false]],
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
    'a rest element' => ['[number, ...string[]]', '$x[1]', null],
    'an unknown member' => ['{ a: unknown }', '$x["a"]', null],
    'a member naming a class' => ['{ a: Post }', '$x["a"]', null],
    'a computed key' => ['{ a: number }', '$x[$k]', null],
    'a holder that is only null' => ['null', '$x["a"]', null],
    'a string' => ['string', '$x["a"]', null],
]);

// A named group keys the groups and a flag nests them, so both still publish only the `[]` beside them, a known gap. A
// call that may not run leaves what the name held, so it writes only over a name nothing else writes, or a string list.
test('preg_match() with no flag and no named group writes a list of strings, but not over a value a skipped call leaves', function () {
    expect(publishedResourceKeyTypes(PregMatchResource::class, Post::class))->toBe([
        'm' => 'string[] | never[]',
        'bare' => 'string[]',
        'named' => 'never[]',
        'offsets' => 'never[]',
        'kept' => 'unknown',
        'maybe' => 'string[] | never[]',
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

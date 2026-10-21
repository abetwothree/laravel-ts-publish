<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BlockScopedLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BlockScopedParameterGapsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BlockScopedReturnsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use Workbench\App\Http\Resources\BlockScopedBindingsResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

/**
 * The classes each `$x->member` read of a body seeded as a Post-backed resource's resolves its receiver to, in source
 * order; null where none is named.
 *
 * @param  array<string, class-string<Model>>  $parameters  each parameter's model, bound before the body is seeded
 * @return list<list<class-string>|null>
 */
function blockScopedReadClasses(string $body, array $parameters = []): array
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
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
    $reads = new NodeFinder()->find($stmts, fn (Node $node): bool => $node instanceof PropertyFetch
        && $node->var instanceof Variable
        && $node->var->name === 'x');

    return array_map(
        fn (Node $read): ?array => $read instanceof PropertyFetch
            ? resolve(ReceiverClassResolver::class)->resolve($read->var, $scope)?->classes
            : null,
        $reads,
    );
}

/**
 * A pinned post by Ann, with one comment by her and one tag, as the block-scoped fixtures read it.
 */
function blockScopedPost(): Post
{
    $user = new User;
    $user->forceFill(['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com']);
    $comment = new Comment;
    $comment->forceFill(['id' => 5, 'content' => 'hi']);
    $comment->setRelation('user', $user);
    $tag = new Tag;
    $tag->forceFill(['id' => 9, 'name' => 'php']);
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana', 'is_pinned' => true]);
    $post->setRelation('author', $user);
    $post->setRelation('comments', new EloquentCollection([$comment]));
    $post->setRelation('tags', new EloquentCollection([$tag]));

    return $post;
}

test('a block-scoped binding types the reads it holds for, and no read outside it', function () {
    DroppedUnionArms::start();

    try {
        $locals = publishedResourceKeyTypes(BlockScopedLocalsResource::class, Post::class);
        $returns = publishedResourceKeyTypes(BlockScopedReturnsResource::class, Post::class);
        $workbench = publishedResourceKeyTypes(BlockScopedBindingsResource::class, Image::class);
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    expect($locals)->toBe([
        'id' => 'number',
        'if_name?' => 'string | null',
        'try_title?' => 'string',
        'shadowed?' => 'number',
        'loop_writer?' => 'string',
        'loop_transformed?' => 'string',
        'coalesced?' => 'string[] | never[]',
        'draft_title?' => 'string',
        'after_block' => 'number',
    ])
        ->and($returns)->toBe([
            'id' => 'number',
            'short_name?' => 'string',
            'closure_block?' => 'string | number',
            'first?' => 'string | number',
        ])
        ->and($workbench)->toBe(['label?' => 'string', 'bytes?' => 'number'])
        // Past its block `$count` holds nothing the scan knows, so `?? 0` leaves its arm out.
        ->and(array_map(fn (array $arm): string => $arm['expression'].' '.$arm['site'], $dropped))
        ->toBe(['$count coalesce-left']);
});

test('binds a block local past its statement, up to its block\'s end or the statement holding its next write', function (string $body, array $classes) {
    expect(blockScopedReadClasses($body))->toBe($classes);
})->with([
    'an if block, read before, inside and after it' => [
        '$x->a; if (true) { $x = $this->author; $x->b; } $x->c;',
        [null, [User::class], null],
    ],
    'a block nested in the span' => [
        'if (true) { $x = $this->author; foreach ([1] as $i) { $x->a; } $x->b; }',
        [[User::class], [User::class]],
    ],
    'a later write nested in the block, which ends the span where its statement starts' => [
        'if (true) { $x = $this->author; $x->a; if (true) { $x = null; } $x->b; }',
        [[User::class], null],
    ],
    'a loop, whose reads before the write may see an earlier pass' => [
        'foreach ([1] as $i) { $x->a; $x = $this->author; $x->b; }',
        [null, [User::class]],
    ],
    'a try block, and its catch' => [
        'try { $x = $this->author; $x->a; } catch (\Exception) { $x->b; }',
        [[User::class], null],
    ],
    'a name another reference writes through' => [
        '$y = &$x; if (true) { $x = $this->author; $y = null; $x->a; }',
        [null],
    ],
    'a write in a closure importing the name by reference' => [
        '$f = function () use (&$x) { $x = null; }; if (true) { $x = $this->author; $f(); $x->a; }',
        [null],
    ],
    'a by-reference foreach variable, which the array writes through' => [
        '$arr = [1]; foreach ($arr as &$x) {} if (true) { $x = $this->author; $arr[0] = null; $x->a; }',
        [null],
    ],
]);

// Its value names another class, so the parameter holds either class past the block, as an untagged write leaves it.
test('ends a parameter\'s binding at a nested write a tag declares another class for, which types the reads it holds for', function () {
    expect(blockScopedReadClasses(
        '$x->a; if (true) { /** @var \Workbench\App\Models\Comment $x */ $x = json_decode(""); $x->b; } $x->c;',
        ['x' => User::class],
    ))->toBe([[User::class], [Comment::class], null]);
});

// Base kept the gap for the capture's read; a body that writes a name drops only an unbound name's outer gaps.
test('a closure in a parameter\'s exit-block gap reads the value the block wrote, before its own write', function () {
    expect(publishedResourceKeyTypes(BlockScopedParameterGapsResource::class, Post::class)['captured?'] ?? null)
        ->toBe('string | number');
});

test('a block local passed to a transform() closure binds the parameter', function () {
    expect(publishedResourceKeyTypes(BlockScopedLocalsResource::class, Post::class)['loop_transformed?'] ?? null)
        ->toBe('string');
});

test('sends what the block-scoped bindings publish', function () {
    $post = blockScopedPost();
    $image = new Image;
    $image->forceFill(['id' => 21, 'url' => 'https://example.com/a.png', 'size_bytes' => 9]);
    $request = Request::create('/', 'GET', ['shadow' => 1, 'draft' => 1, 'closure' => 1, 'size' => 1]);

    expect(json_encode(new BlockScopedLocalsResource($post)->resolve($request)))->toBe(json_encode([
        'id' => 7,
        'if_name' => 'Ann',
        'try_title' => 'Banana',
        'shadowed' => 7,
        'loop_writer' => 'Ann',
        'loop_transformed' => 'Ann',
        'coalesced' => ['php'],
        'draft_title' => 'Draft',
        'after_block' => 1,
    ]))
        ->and(json_encode(new BlockScopedReturnsResource($post)->resolve($request)))->toBe(json_encode([
            'id' => 7,
            'closure_block' => 'Banana',
            'first' => 'hi',
        ]))
        ->and(json_encode(new BlockScopedReturnsResource($post)->resolve(Request::create('/', 'GET', ['short' => 1]))))
        ->toBe(json_encode(['id' => 7, 'short_name' => 'Ann']))
        ->and(json_encode(new BlockScopedBindingsResource($image)->resolve($request)))->toBe(json_encode([
            'label' => 'https://example.com/a.png',
            'bytes' => 9,
        ]))
        ->and(json_encode(new BlockScopedParameterGapsResource($post)->resolve($request)))
        ->toBe(json_encode(['captured' => 'Ann', 'passed' => 'Ann']));
});

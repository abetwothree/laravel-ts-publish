<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedCollectResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedDeclaredLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLastStatementClassVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLastStatementPostVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLastStatementVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLeftOutResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedLocalsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedNullMemberResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedOpenModelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedParametersResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedRelativeTagResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedThisTagResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedThisTagUserResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JoinedTopLevelVarResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AccessorTitleUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\GetterTitleUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MagicTitleUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ThisTaggedUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LooseName;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/**
 * A post titled Banana, pinned or not, with options a=x and an empty b, one comment `hi` (id 5) and its author Ann.
 */
function joinedPost(bool $pinned): Post
{
    $comment = new Comment;
    $comment->forceFill(['id' => 5, 'content' => 'hi']);
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana', 'is_pinned' => $pinned, 'options' => ['a' => 'x', 'b' => '']]);
    $post->setRelation('comments', new EloquentCollection([$comment]));
    $post->setRelation('author', new User()->forceFill(['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com']));

    return $post;
}

/**
 * What Laravel sends for a joined fixture over the post, pinned or not.
 *
 * @param  class-string<JsonResource>  $resource
 * @return array<string, mixed>
 */
function joinedSends(string $resource, bool $pinned): array
{
    /** @var array<string, mixed> $sent */
    $sent = json_decode((string) json_encode(new $resource(joinedPost($pinned))->resolve(Request::create('/'))), true);

    return $sent;
}

/**
 * A user (id 3) with one post titled Banana (id 7), or a guest (id 0) with none.
 */
function joinedUser(bool $guest): User
{
    $post = new Post;
    $post->forceFill(['id' => 7, 'title' => 'Banana']);
    $user = new User()->forceFill(['id' => $guest ? 0 : 3, 'name' => 'Ann']);
    $user->setRelation('posts', new EloquentCollection($guest ? [] : [$post]));

    return $user;
}

/**
 * What Laravel sends for the declared-locals fixture over a user or a guest.
 *
 * @return array<string, mixed>
 */
function joinedDeclaredSends(bool $guest): array
{
    $resource = new JoinedDeclaredLocalsResource(joinedUser($guest));
    /** @var array<string, mixed> $sent */
    $sent = json_decode((string) json_encode($resource->resolve(Request::create('/'))), true);

    return $sent;
}

/**
 * Each run-output warning about an arm a join left out, as its line and the expression it names.
 *
 * @return list<string>
 */
function joinedDropWarnings(): array
{
    return array_values(array_map(
        fn (array $warning): string => (string) preg_replace('/^The value on line (\d+) leaves out `(.+?)`, .*$/s', '$1 $2', $warning['message']),
        AnalysisWarnings::all(),
    ));
}

/**
 * The warning for `$this` a join left out where the class it is read as does not declare the member, or declares it
 * with no type.
 */
function joinedThisDrop(int $line, string $class, string $member, bool $declared): string
{
    return $declared
        ? "The value on line {$line} leaves out `\$this`, read as `{$class}`, whose `{$member}` could not be typed, so its "
            .'published type may not admit every value it sends. Type it, or give the local a `@var` naming a class that types it.'
        : "The value on line {$line} leaves out `\$this`, read as `{$class}`, which does not declare `{$member}`, so its "
            .'published type may not admit every value it sends. Give the local a `@var` naming a class that declares it.';
}

test('a local written in sequence and inside branches joins the values that can reach the read', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'status' => 'string | number',
        'first' => 'string | null',
        'removed' => 'unknown',
        'captured' => 'unknown',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, true))->toMatchArray(['status' => 1, 'first' => 'hi'])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['status' => 'draft', 'removed' => 'kept', 'captured' => 'kept']);
});

// `?->` reads a joined `null` as null, so the `null` adds no class to the receiver, written before the model or after.
test('a member read past a join leaves out a joined null', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))
        ->toMatchArray(['author_name' => 'string | null', 'editor_name' => 'string | null'])
        ->and(joinedSends(JoinedLocalsResource::class, true))
        ->toMatchArray(['author_name' => 'Ann', 'editor_name' => null])
        ->and(joinedSends(JoinedLocalsResource::class, false))
        ->toMatchArray(['author_name' => null, 'editor_name' => 'Ann']);
});

// On the `null` path `->` raises a warning Laravel throws as an ErrorException, so only the model's member is sent.
test('a plain member read past a joined null reads the model\'s member', function () {
    $request = Request::create('/');

    expect(publishedResourceKeyTypes(JoinedNullMemberResource::class, Post::class))->toBe(['editor_name' => 'string'])
        ->and(fn () => new JoinedNullMemberResource(joinedPost(true))->resolve($request))
        ->toThrow(ErrorException::class, 'Attempt to read property "name" on null')
        ->and(new JoinedNullMemberResource(joinedPost(false))->resolve($request))->toBe(['editor_name' => 'Ann']);
});

test('a local the try body and a catch both assign joins both, and a finally write replaces them', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'caught' => 'string | null',
        'finally' => 'number',
        'caught_read' => 'string | number',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, true))->toMatchArray(['caught' => null, 'finally' => 1, 'caught_read' => 'Banana'])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['caught' => 'Banana', 'finally' => 1, 'caught_read' => 7]);
});

// An old value no rule types is left out beside the write's, as a ternary leaves out an arm, and so is the value of a
// call no rule types, which binds the callback's parameter to nothing.
test('a parameter written inside a block joins its old value and the write\'s', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'joined?' => 'string | number',
        'vague_joined?' => 'number',
        'opaque_joined?' => 'number',
        'caught_joined?' => 'string | number',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['joined' => 5, 'vague_joined' => 5, 'opaque_joined' => 5, 'caught_joined' => 'Banana'])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['caught_joined' => 3]);
});

// A variable passed in a gap whose old value may still hold passes both, for the value and for a receiver alike.
test('a parameter passed on past a nested write passes its old value and the write\'s', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'passed_joined?' => 'string | number',
        'passed_joined_id?' => 'number',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))->toMatchArray(['passed_joined' => 7, 'passed_joined_id' => 5])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['passed_joined' => 'Banana', 'passed_joined_id' => 3]);
});

// The copy keeps which of the gap's values a nested write added, so one the engine cannot type is left out there too.
test('a parameter passed on past a nested write the engine cannot type passes its old value', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray(['passed_kept?' => 'string'])
        ->and(joinedSends(JoinedParametersResource::class, true))->not->toHaveKey('passed_kept')
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['passed_kept' => 'Banana']);
});

// The value a parameter's own write left still reaches past a nested write, so one the engine cannot type is left out.
// With no earlier value reaching, past a top-level write or a `try` and `catch` that both write, it reads unknown.
test('a parameter past its own write leaves out a nested write the engine cannot type', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'post_write_nested?' => 'string',
        'post_write_nested_typed?' => 'string | number',
        'both_paths?' => 'unknown',
        'expr_value?' => 'unknown',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['post_write_nested' => null, 'post_write_nested_typed' => 6, 'both_paths' => [], 'expr_value' => 's'])
        ->and(joinedSends(JoinedParametersResource::class, false))
        ->toMatchArray(['post_write_nested' => 'Banana', 'post_write_nested_typed' => 'Banana']);
});

// Each arm a join leaves out is the audit's: the old value no rule types, a property's or the value of a call no rule
// types, and a nested write no rule types while an earlier value still reaches the read.
test('a parameter\'s join records each arm it leaves out', function () {
    DroppedUnionArms::start();

    try {
        publishedResourceKeyTypes(JoinedParametersResource::class, Post::class);
    } finally {
        $dropped = DroppedUnionArms::stop();
    }

    expect(array_map(fn (array $arm): string => $arm['line'].' '.$arm['expression'].' '.$arm['site'], $dropped))->toBe([
        '44 $this->vague binding-join',
        '71 json_decode(\'{}\') binding-join',
        '76 $this->opaque() binding-join',
        '111 json_decode($title) binding-join',
        '144 json_decode($title) binding-join',
        '212 $this->whenHas(\'missing_column\') binding-join',
    ]);
});

// The value left out is a MissingValue for a pinned post, so Laravel leaves the key out there.
test('a value a parameter\'s join leaves out keeps the key optional where it may be missing', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray(['helper_missing?' => 'string'])
        ->and(joinedSends(JoinedParametersResource::class, true))->not->toHaveKey('helper_missing')
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['helper_missing' => 'Banana']);
});

// The declared string holds for a read before the body's write, and joins a branch's write for a read past it.
test('a helper\'s parameter the body writes reads its declared value before the write, and a branch\'s write past it', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'helper_joined' => 'string | number',
        'helper_copy' => 'string',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))->toMatchArray(['helper_joined' => 7, 'helper_copy' => 'Banana'])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray(['helper_joined' => 'Banana']);
});

// The tag names another model, so past the block a read joins the old model and the tag's, for a member and a value.
test('a parameter joins the model a nested @var declares for its write past its block', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))
        ->toMatchArray(['declared_joined_id?' => 'number | null', 'declared_joined?' => 'User | Comment | null'])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['declared_joined_id' => 5, 'declared_joined' => ['id' => 5, 'content' => 'hi']])
        ->and(joinedSends(JoinedParametersResource::class, false))->toMatchArray([
            'declared_joined_id' => 3,
            'declared_joined' => ['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com'],
        ]);
});

// A written value no rule types is left out of the join, as a receiver keeps the old binding past it.
test('a parameter keeps its old value past a nested write the engine cannot type', function () {
    expect(publishedResourceKeyTypes(JoinedParametersResource::class, Post::class))->toMatchArray([
        'kept_upper?' => 'string',
        'kept_filtered?' => 'Record<string, string> | null',
        'kept_author?' => 'User',
    ])
        ->and(joinedSends(JoinedParametersResource::class, true))
        ->toMatchArray(['kept_upper' => 'BANANA', 'kept_filtered' => ['a' => 'x'], 'kept_author' => []])
        ->and(joinedSends(JoinedParametersResource::class, false))
        ->toMatchArray(['kept_upper' => 'Banana', 'kept_filtered' => ['a' => 'x', 'b' => ''], 'kept_author' => ['id' => 3, 'name' => 'Ann', 'email' => 'ann@example.com']]);
});

// The tag's write is the block's last statement, so past the block the old value or the tag's may reach the read.
test('a parameter\'s @var on its block\'s last statement joins the old value', function () {
    expect(publishedResourceKeyTypes(JoinedLastStatementVarResource::class, Post::class))
        ->toMatchArray(['param_last?' => 'string | number', 'param_not_last?' => 'string | number'])
        ->and(joinedSends(JoinedLastStatementVarResource::class, true))->toMatchArray(['param_last' => 5, 'param_not_last' => 5])
        ->and(joinedSends(JoinedLastStatementVarResource::class, false))
        ->toMatchArray(['param_last' => 'Banana', 'param_not_last' => 'Banana']);
});

// A model without the attribute reads it as null, so each model of the join answers: the user has no `content` and the
// comment no `name`.
test('a member read past a parameter\'s last-statement @var reads each class', function () {
    expect(publishedResourceKeyTypes(JoinedLastStatementVarResource::class, Post::class))->toMatchArray([
        'param_last_class?' => 'string | null',
        'name_last?' => 'string | null',
        'name_last_untyped?' => 'string | null',
    ])
        ->and(joinedSends(JoinedLastStatementVarResource::class, true))
        ->toMatchArray(['param_last_class' => 'hi', 'name_last' => null, 'name_last_untyped' => null])
        ->and(joinedSends(JoinedLastStatementVarResource::class, false))
        ->toMatchArray(['param_last_class' => null, 'name_last' => 'Ann', 'name_last_untyped' => 'Ann']);
});

// A class the tag names that cannot answer the read gives way to the old model's, with a warning naming its write; a
// plain object that types the member joins.
test('a parameter\'s last-statement @var naming a class other than a concrete model keeps the old model\'s member', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedLastStatementClassVarResource::class, Post::class))->toBe([
        'auth_name?' => 'string',
        'model_email?' => 'string',
        'object_name?' => 'string',
        'object_name_untyped?' => 'string',
        'numbered_name?' => 'string | number',
        'auth_key?' => 'number',
        'auth_year?' => 'string | null',
    ])
        ->and(joinedDropWarnings())->toBe([
            '34 $this->opaque($this->author)',
            '42 $this->opaque($this->author)',
            '50 $this->opaque(new \\'.CartTotals::class.'(1.5, true, 2))',
            '58 $this->opaque(new \\'.CartTotals::class.'(1.5, true, 2))',
            '74 $this->opaque($this->author)',
            '82 $this->opaque($this->author)',
        ])
        ->and(joinedSends(JoinedLastStatementClassVarResource::class, false))->toBe([
            'auth_name' => 'Ann',
            'model_email' => 'ann@example.com',
            'object_name' => 'Ann',
            'object_name_untyped' => 'Ann',
            'numbered_name' => 'Ann',
            'auth_key' => 3,
            'auth_year' => null,
        ])
        ->and(fn () => joinedSends(JoinedLastStatementClassVarResource::class, true))
        ->toThrow(ErrorException::class, 'Undefined property');
});

// Each user may answer `title` with a value no rule types, so its arm gives way to the post's, with a warning.
test('a parameter\'s last-statement @var naming a model that declines the member keeps the old model\'s member', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedLastStatementPostVarResource::class, Post::class))->toBe([
        'accessor_title?' => 'string',
        'magic_title?' => 'string',
        'getter_title?' => 'string',
    ])
        ->and(joinedDropWarnings())->toBe([
            '31 $this->opaque((new \\'.AccessorTitleUser::class.'())->forceFill([\'id\' => 1]))',
            '39 $this->opaque((new \\'.MagicTitleUser::class.'())->forceFill([\'id\' => 2]))',
            '47 $this->opaque((new \\'.GetterTitleUser::class.'())->forceFill([\'id\' => 3]))',
        ])
        ->and(joinedSends(JoinedLastStatementPostVarResource::class, true))
        ->toBe(['accessor_title' => 1, 'magic_title' => 123, 'getter_title' => 1])
        ->and(joinedSends(JoinedLastStatementPostVarResource::class, false))
        ->toBe(['accessor_title' => 'Banana', 'magic_title' => 'Banana', 'getter_title' => 'Banana']);
});

// The callback's parameter takes the joined collection's element, so its read warns of the write the join left out.
test('a callback mapping a joined collection warns of the write the join left out', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedMapResource::class, Post::class))
        ->toBe(['contents' => 'string[]', 'sorted_contents' => 'string[]'])
        ->and(joinedDropWarnings())->toBe(['27 $this->opaque($this->comments)'])
        ->and(joinedSends(JoinedMapResource::class, true))->toBe(['contents' => ['hi'], 'sorted_contents' => ['hi']]);
});

// A `static`, `self` or `parent` tag gives way like any other where the resource it names cannot answer the member.
test('a parameter\'s last-statement relative @var that declines the member keeps the old model\'s member', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedRelativeTagResource::class, Post::class))->toBe([
        'static_name?' => 'string',
        'self_name?' => 'string',
        'parent_name?' => 'string',
        'static_key?' => 'number',
        'static_title?' => 'string | null',
    ])
        ->and(joinedDropWarnings())->toBe(['29 $this->opaque($a)', '37 $this->opaque($a)', '45 $this->opaque($a)'])
        ->and(joinedSends(JoinedRelativeTagResource::class, true))
        ->toBe(['static_name' => 'Ann', 'self_name' => 'Ann', 'parent_name' => 'Ann', 'static_key' => 3, 'static_title' => null]);
});

// The resource declares no `name` and forwards it to the post, and the value object declares none of the members, so
// `$this` gives way to the user, with a warning naming the class it is read as; members the post answers join. A class
// declaring the member untyped gives way too, a parameter's `self` included, with a warning that it could not be typed.
test('a parameter\'s last-statement @var over $this whose class cannot answer the member keeps the old value\'s member', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedThisTagResource::class, Post::class))->toBe([
        'static_name?' => 'string',
        'self_name?' => 'string',
        'parent_name?' => 'string',
        'totals_name?' => 'string',
        'static_title?' => 'string | null',
        'static_key?' => 'number',
        'totals_key?' => 'number',
        'totals_year?' => 'string | null',
        'loose_name?' => 'string',
        'self_label' => 'string',
    ])
        ->and(AnalysisWarnings::all())->toBe(array_map(fn (string $message): array => [
            'subject' => JoinedThisTagResource::class,
            'message' => $message,
        ], [
            joinedThisDrop(32, JoinedThisTagResource::class, 'name', false),
            joinedThisDrop(40, JoinedThisTagResource::class, 'name', false),
            joinedThisDrop(48, JsonResource::class, 'name', false),
            joinedThisDrop(56, CartTotals::class, 'name', false),
            joinedThisDrop(80, CartTotals::class, 'getKey()', false),
            joinedThisDrop(88, CartTotals::class, 'created_at', false),
            joinedThisDrop(96, LooseName::class, 'name', true),
            joinedThisDrop(116, JoinedThisTagResource::class, 'label()', true),
        ]))
        ->and(joinedSends(JoinedThisTagResource::class, true))->toBe([
            'static_name' => null, 'self_name' => null, 'parent_name' => null, 'totals_name' => null,
            'static_title' => 'Banana', 'static_key' => 7, 'totals_key' => 7, 'totals_year' => null,
            'loose_name' => null, 'self_label' => 1,
        ])
        ->and(joinedSends(JoinedThisTagResource::class, false))->toBe([
            'static_name' => 'Ann', 'self_name' => 'Ann', 'parent_name' => 'Ann', 'totals_name' => 'Ann',
            'static_title' => null, 'static_key' => 3, 'totals_key' => 3, 'totals_year' => null,
            'loose_name' => 'Ann', 'self_label' => 'v',
        ]);
});

// A model's method reads the same way: the value object the tag names declares no `name`, while the model types it.
// In a union tag only `static` keeps a reading by declaration, so the value object beside it gives way.
test('a model method\'s @var over $this naming a class that cannot answer the member keeps the other values\' member', function () {
    AnalysisWarnings::reset();
    $user = new ThisTaggedUser()->forceFill(['id' => 3, 'name' => 'Ann']);

    expect(publishedResourceKeyTypes(JoinedThisTagUserResource::class, ThisTaggedUser::class))
        ->toBe(['static_name' => 'string', 'totals_name' => 'string', 'union_name' => 'number | string'])
        ->and(array_column(AnalysisWarnings::all(), 'message'))->toBe([
            joinedThisDrop(33, CartTotals::class, 'name', false),
            joinedThisDrop(46, LooseName::class, 'name', true),
        ])
        ->and(new JoinedThisTagUserResource($user)->resolve(Request::create('/')))
        ->toBe(['static_name' => 'Ann', 'totals_name' => 'Ann', 'union_name' => 'Ann']);
});

// collect() wraps the joined collection, so its callback's parameter takes the element with what the join left out.
test('a collect() pipeline over a joined collection warns of the write the join left out', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedCollectResource::class, Post::class))->toBe([
        'contents' => 'string[] | Record<string, string>',
        'items' => '{ c: string }[] | Record<string, { c: string }>',
    ])
        ->and(joinedDropWarnings())->toBe(['27 $this->opaque($this->comments)'])
        ->and(joinedSends(JoinedCollectResource::class, true))->toBe(['contents' => ['hi'], 'items' => [['c' => 'hi']]]);
});

// Each of these models may answer the member through an override or a subclass, so the read stays unknown.
test('a member read past a join declines where an arm\'s model may answer a member it does not list', function () {
    expect(publishedResourceKeyTypes(JoinedOpenModelResource::class, Post::class))->toBe([
        'magic_title' => 'unknown',
        'attribute_title' => 'unknown',
        'any_content' => 'unknown',
        'morphed_content' => 'unknown',
    ])
        ->and(joinedSends(JoinedOpenModelResource::class, true))
        ->toBe(['magic_title' => 123, 'attribute_title' => 456, 'any_content' => 'any', 'morphed_content' => 'morphed'])
        ->and(joinedSends(JoinedOpenModelResource::class, false))
        ->toBe(['magic_title' => 'Banana', 'attribute_title' => 'Banana', 'any_content' => 'hi', 'morphed_content' => 'hi']);
});

// A member read of a string or an array throws, so those writes are left out quietly, while an object no class names
// warns; the untyped value is left out of a read that publishes nothing, so nothing warns of it.
test('a join leaves out a typed write naming no class quietly, and warns of an untyped one only where the read is typed', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedLeftOutResource::class, Post::class))->toBe([
        'guest_name' => 'string',
        'titled_name' => 'string',
        'listed_name' => 'string',
        'object_name' => 'string',
        'opaque_title' => 'unknown',
    ])
        ->and(joinedDropWarnings())->toBe(['45 (object) [\'name\' => 5]'])
        ->and(joinedSends(JoinedLeftOutResource::class, false))
        ->toBe(['guest_name' => 'Ann', 'titled_name' => 'Ann', 'listed_name' => 'Ann', 'object_name' => 'Ann', 'opaque_title' => null])
        ->and(fn () => joinedSends(JoinedLeftOutResource::class, true))
        ->toThrow(ErrorException::class, 'Attempt to read property "name" on string');
});

// The later write's value no rule types is left out beside the tag's, with a warning naming it.
test('a top-level @var joins a later nested write', function () {
    AnalysisWarnings::reset();

    expect(publishedResourceKeyTypes(JoinedTopLevelVarResource::class, Post::class))->toBe([
        'top_var_nested_write' => 'string',
        'top_var_nested_number' => 'number | string',
        'top_var_nested_typed' => 'string',
    ])
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => JoinedTopLevelVarResource::class,
            'message' => 'The value on line 29 leaves out `$this->opaque($this->comments->last())`, which could not be typed, '
                .'so its published type may not admit every value it sends. Give what it reads a return type or a `@return` docblock.',
        ]])
        ->and(joinedSends(JoinedTopLevelVarResource::class, true))
        ->toBe(['top_var_nested_write' => 'hi', 'top_var_nested_number' => 'x', 'top_var_nested_typed' => 'hi'])
        ->and(joinedSends(JoinedTopLevelVarResource::class, false))
        ->toBe(['top_var_nested_write' => 'hi', 'top_var_nested_number' => 1, 'top_var_nested_typed' => 'hi']);
});

test('a nested @var types the value a join carries past its block', function () {
    expect(publishedResourceKeyTypes(JoinedDeclaredLocalsResource::class, User::class))
        ->toMatchArray(['t' => 'string | null', 'post' => 'Post | null'])
        ->and(joinedDeclaredSends(false))->toMatchArray(['t' => 'Banana', 'post' => ['id' => 7, 'title' => 'Banana']])
        ->and(joinedDeclaredSends(true))->toMatchArray(['t' => null, 'post' => null]);
});

// data_get() of one key reads the relation, which the value takes; the tag types the receiver it names nothing for.
test('a nested @var naming a collection types the collection a join carries past its block', function () {
    expect(publishedResourceKeyTypes(JoinedDeclaredLocalsResource::class, User::class))
        ->toMatchArray(['posts' => 'Post[] | null', 'posts_first_title' => 'string | null'])
        ->and(joinedDeclaredSends(false))
        ->toMatchArray(['posts' => [['id' => 7, 'title' => 'Banana']], 'posts_first_title' => 'Banana'])
        ->and(joinedDeclaredSends(true))->toMatchArray(['posts' => null, 'posts_first_title' => null]);
});

// The tag declares `$other`, not `$o`, so the join reads data_get() alone, though Laravel sends the title.
test('a nested @var naming another variable leaves the join\'s own reading', function () {
    expect(publishedResourceKeyTypes(JoinedDeclaredLocalsResource::class, User::class))
        ->toMatchArray(['other_title' => 'unknown'])
        ->and(joinedDeclaredSends(false))->toMatchArray(['other_title' => 'Banana'])
        ->and(joinedDeclaredSends(true))->toMatchArray(['other_title' => null]);
});

test('a local another name or a closure can write through a reference is never joined', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'aliased' => 'unknown',
        'closed' => 'unknown',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['aliased' => 1, 'closed' => 1]);
});

test('a variable variable\'s write is no write of the variable that names it', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray(['key' => 'string'])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['key' => 'a']);
});

test('a local that reads itself in its own reassignment keeps both values', function () {
    expect(publishedResourceKeyTypes(JoinedLocalsResource::class, Post::class))->toMatchArray([
        'self' => 'string',
        'wrapped' => '{ v: string }',
    ])
        ->and(joinedSends(JoinedLocalsResource::class, false))->toMatchArray(['self' => 'aBanana', 'wrapped' => ['v' => 'Banana']]);
});

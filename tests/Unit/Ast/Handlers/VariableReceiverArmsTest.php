<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BoundEnumColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CapturedLocalClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedEnumColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedProfileWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NarrowedBoundPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SupertypeGuardedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UnboundClosureVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DraftPost;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/** What Laravel sends for one key of MappedProfileWrapResource over a team whose second member has no profile. */
function mappedProfileWrapSends(string $key): string
{
    $withProfile = (new User)->forceFill(['id' => 1, 'name' => 'a']);
    $withProfile->setRelation('profile', (new Profile)->forceFill(['id' => 5, 'user_id' => 1]));
    $without = (new User)->forceFill(['id' => 2, 'name' => 'b']);
    $without->setRelation('profile', null);
    $team = (new Team)->forceFill(['id' => 9]);
    $team->setRelation('members', new EloquentCollection([$withProfile, $without]));
    $team->setRelation('owner', $without);

    $value = new MappedProfileWrapResource($team)->toArray(request())[$key];
    $sent = new class($value) extends JsonResource
    {
        /** @return array<string, mixed> */
        public function toArray(Request $request): array
        {
            return ['k' => $this->resource];
        }
    };

    try {
        return (string) $sent->response(request())->getContent();
    } catch (Throwable) {
        return 'throws';
    }
}

// A read through a variable no writer binds goes on to the receiver handlers, which never guess the relation's model;
// the same reads through the relation's own parameter keep its model.
it('types a variable read inside whenLoaded() only from the variable\'s own binding', function () {
    expect(publishedResourceKeyTypes(UnboundClosureVariableResource::class, Post::class))->toBe([
        'unbound_prop?' => 'unknown',
        'unbound_method?' => 'unknown',
        'unbound_pluck?' => 'unknown',
        'static_guess?' => 'unknown',
        'static_bound?' => 'boolean',
        'to_resource_bound?' => 'UserResource',
        'enum_bound?' => 'AsEnum<typeof Role>',
        'to_resource_guess?' => 'unknown',
        'enum_guess?' => 'unknown',
        'local_post?' => 'string',
        'author_relations?' => '{ posts: Post[]; profile: Profile | null }',
        's_author' => 'User',
        's_comments' => 'Comment[]',
    ]);
});

// JsonResource::filter() recurses into arrays only: inside a Collection the null-payload wrap serializes itself.
it('adds a wrap\'s null arm only where filter() reaches the mapped items', function () {
    expect(publishedResourceKeyTypes(MappedProfileWrapResource::class, Team::class))->toBe([
        'profiles?' => '(Profile | null)[]',
        'wrapped?' => '{ profile: ProfileResource }[]',
        'wrapped_values?' => '{ profile: ProfileResource }[]',
        'wrapped_all?' => '({ profile: ProfileResource | null })[]',
        'chain_wrapped' => '{ profile: ProfileResource }[]',
        'chain_wrapped_all' => '({ profile: ProfileResource | null })[]',
        'pipe_owner_wrapped' => '{ profile: ProfileResource }[]',
        'pipe_owner_wrapped_all' => '({ profile: ProfileResource | null })[]',
        'pipe_member_wrapped' => '{ profile: ProfileResource }[]',
        'pipe_member_wrapped_all' => '({ profile: ProfileResource | null })[]',
        'owner_wrapped?' => '{ profile: ProfileResource | null }',
    ])
        ->and(mappedProfileWrapSends('wrapped'))->toBe('throws')
        ->and(mappedProfileWrapSends('wrapped_values'))->toBe('throws')
        ->and(mappedProfileWrapSends('chain_wrapped'))->toBe('throws')
        ->and(mappedProfileWrapSends('pipe_owner_wrapped'))->toBe('throws')
        ->and(mappedProfileWrapSends('pipe_member_wrapped'))->toBe('throws')
        ->and(mappedProfileWrapSends('wrapped_all'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('chain_wrapped_all'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('pipe_owner_wrapped_all'))->toBe('{"data":{"k":[{"profile":null},{"profile":null}]}}')
        ->and(mappedProfileWrapSends('pipe_member_wrapped_all'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('owner_wrapped'))->toBe('{"data":{"k":{"profile":null}}}');
});

it('a captured outer local keeps its own binding inside a whenLoaded() closure', function () {
    expect(publishedResourceKeyTypes(CapturedLocalClosureResource::class, Post::class))->toBe([
        'captured_use?' => 'string',
        'captured_arrow?' => 'string',
    ]);
});

// Only inside a relation closure does the enum branch drop the column's null; anywhere else the read keeps it.
it('keeps a nullable enum column\'s null arm for a model bound outside a relation closure', function () {
    $owner = (new User)->forceFill(['id' => 1, 'role' => null]);
    $team = (new Team)->forceFill(['id' => 9]);
    $team->setRelation('owner', $owner);
    $team->setRelation('members', new EloquentCollection([$owner]));

    expect(publishedResourceKeyTypes(BoundEnumColumnResource::class, Team::class))->toBe([
        'owner_role?' => 'AsEnum<typeof Role> | null',
        'member_role?' => 'AsEnum<typeof Role> | null',
    ])
        ->and(new BoundEnumColumnResource($team)->response(request())->getContent())
        ->toBe('{"data":{"owner_role":null,"member_role":null}}');
});

// Only a relation closure, a hinted map among them, drops the column's null (F24, for the owner); a map over a query,
// a local or a collect() of a relation keeps it, as Laravel sends it, and so does one nested in a relation closure. A
// collect() pipeline nested in one keeps the enclosing closure's drop (F24).
it('keeps a nullable enum column\'s null arm in a map over a query, a local or a collect() of a relation', function () {
    $member = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $team = (new Team)->forceFill(['id' => 9]);
    $team->setRelation('members', new EloquentCollection([$member]));
    $team->setRelation('owner', $member);

    expect(publishedResourceKeyTypes(MappedEnumColumnResource::class, Team::class))->toBe([
        'query_roles' => '(AsEnum<typeof Role> | null)[]',
        'local_roles' => '(AsEnum<typeof Role> | null)[]',
        'hinted_local_roles' => 'AsEnum<typeof Role>[]',
        'collected_roles' => '(AsEnum<typeof Role> | null)[]',
        'chain_roles' => 'AsEnum<typeof Role>[]',
        'nested_query_roles?' => '(AsEnum<typeof Role> | null)[]',
        'nested_local_roles?' => '(AsEnum<typeof Role> | null)[]',
        'nested_collected_roles?' => 'AsEnum<typeof Role>[]',
    ])
        ->and(json_decode((string) new MappedEnumColumnResource($team)->response(request())->getContent(), true))
        ->toBe(['data' => [
            'query_roles' => [null],
            'local_roles' => [null],
            'hinted_local_roles' => [null],
            'collected_roles' => [null],
            'chain_roles' => [null],
            'nested_query_roles' => [null],
            'nested_local_roles' => [null],
            'nested_collected_roles' => [null],
        ]]);
});

// The narrowing outranks the parameter's own model, so the subclass types the read.
it('reads a model-bound parameter under an instanceof test as the subclass it narrows to', function () {
    $draft = (new DraftPost)->forceFill(['title' => 'abc', 'word_count' => 12]);
    $post = (new Post)->forceFill(['title' => 'abc', 'word_count' => 12]);

    expect(publishedResourceKeyTypes(NarrowedBoundPostResource::class, Post::class))->toBe([
        'word_count' => 'string | null',
        'shouted_title' => 'string | null',
    ])
        ->and(new NarrowedBoundPostResource($draft)->response(request())->getContent())
        ->toBe('{"data":{"word_count":"12","shouted_title":"ABC"}}')
        ->and(new NarrowedBoundPostResource($post)->response(request())->getContent())
        ->toBe('{"data":{"word_count":null,"shouted_title":null}}');
});

// A test for a class the binding already is proves nothing narrower, so the bound model and collection still type.
it('keeps a bound model or collection under an instanceof test for a class it already is', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id, 'word_count' => 12]);

    foreach (['alpha', 'beta'] as $content) {
        Comment::create(['content' => $content, 'post_id' => $post->id, 'user_id' => $user->id]);
    }

    /** @var array{data: array<string, mixed>} $sent */
    $sent = json_decode((string) new SupertypeGuardedPostResource(Post::with('comments')->findOrFail($post->id))
        ->response(request())->getContent(), true);

    expect(publishedResourceKeyTypes(SupertypeGuardedPostResource::class, Post::class))->toBe([
        'model_guarded_title' => 'string | null',
        'model_guarded_word_count' => 'number | null',
        'interface_guarded_title' => 'string | null',
        'model_ternary_title' => 'string | null',
        'spread_title?' => 'string',
        'spread_publishable?' => 'boolean',
        'narrowed_contents?' => 'string[] | null',
        'narrowed_spread?' => 'Comment[] | null',
    ])->and($sent['data'])->toMatchArray([
        'model_guarded_title' => 'First',
        'model_guarded_word_count' => 12,
        'interface_guarded_title' => 'First',
        'model_ternary_title' => 'First',
        'spread_title' => 'First',
        'spread_publishable' => false,
        'narrowed_contents' => ['alpha', 'beta'],
    ])->and(array_column($sent['data']['narrowed_spread'], 'content'))->toBe(['alpha', 'beta']);
});

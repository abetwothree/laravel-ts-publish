<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\BoundEnumColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CapturedLocalClosureResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GuardedDraftCommentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\GuardedLoopPostsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InterfaceGuardedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeptEnumMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\KeptOwnRoleMapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\LoadedOrderEnumColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedEnumColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedOrderEnumColumnResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedProfileWrapResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NarrowedBoundPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SupertypeGuardedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UnboundClosureVariableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\CountedPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DraftPost;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\OrderStatus;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\WeekDays;
use Workbench\App\Http\Resources\ConditionalParamEnumResource;
use Workbench\App\Http\Resources\EnumCollectionResource;
use Workbench\App\Http\Resources\RelationChainResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
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
        'enum_bound?' => 'AsEnum<typeof Role> | null',
        'to_resource_guess?' => 'unknown',
        'enum_guess?' => 'unknown',
        'local_post?' => 'string',
        'author_relations?' => '{ posts: Post[]; profile: Profile | null }',
        's_author' => 'User',
        's_comments' => 'Comment[]',
    ]);
});

// JsonResource::filter() recurses into arrays only: inside a Collection the null-payload wrap serializes itself.
// `->toArray()` keeps each wrap for filter(), as a helper on another class does; `->jsonSerialize()` stays unknown.
it('adds a wrap\'s null arm only where filter() reaches the mapped items', function () {
    expect(publishedResourceKeyTypes(MappedProfileWrapResource::class, Team::class))->toBe([
        'profiles?' => '(Profile | null)[]',
        'wrapped?' => '{ profile: ProfileResource }[]',
        'wrapped_values?' => '{ profile: ProfileResource }[]',
        'wrapped_all?' => '{ profile: ProfileResource | null }[]',
        'chain_wrapped' => '{ profile: ProfileResource }[]',
        'chain_wrapped_all' => '{ profile: ProfileResource | null }[]',
        'pipe_owner_wrapped' => '{ profile: ProfileResource }[]',
        'pipe_owner_wrapped_all' => '{ profile: ProfileResource | null }[]',
        'pipe_member_wrapped' => '{ profile: ProfileResource }[]',
        'pipe_member_wrapped_all' => '{ profile: ProfileResource | null }[]',
        'owner_wrapped?' => '{ profile: ProfileResource | null }',
        'wrapped_to_array?' => '{ profile: ProfileResource | null }[]',
        'wrapped_serialized?' => 'unknown',
        'direct?' => 'ProfileResource[]',
        'direct_all?' => '(ProfileResource | null)[]',
        'direct_to_array?' => '(ProfileResource | null)[]',
        'chain_direct_to_array' => '(ProfileResource | null)[]',
        'pipe_direct_to_array' => '(ProfileResource | null)[]',
        'helper_owner_profile' => 'ProfileResource | null',
    ])
        ->and(mappedProfileWrapSends('wrapped_to_array'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('wrapped_serialized'))->toContain('{"profile":null}')
        ->and(mappedProfileWrapSends('direct'))->toBe('throws')
        ->and(mappedProfileWrapSends('direct_all'))->toContain(',null]')
        ->and(mappedProfileWrapSends('direct_to_array'))->toContain(',null]')
        ->and(mappedProfileWrapSends('chain_direct_to_array'))->toContain(',null]')
        ->and(mappedProfileWrapSends('pipe_direct_to_array'))->toContain(',null]')
        ->and(mappedProfileWrapSends('helper_owner_profile'))->toBe('{"data":{"k":null}}')
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

// The null arm follows JsonResource::filter()'s reach, inside a relation closure or not: a map `->all()` hands to it
// sends null, and one kept as a Collection serializes each wrap itself, sending the `[]` resolve() returns.
it('keeps a nullable enum column\'s null arm in a map filter() reaches, inside a relation closure or not', function () {
    $member = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $team = (new Team)->forceFill(['id' => 9]);
    $team->setRelation('members', new EloquentCollection([$member]));
    $team->setRelation('owner', $member);

    expect(publishedResourceKeyTypes(MappedEnumColumnResource::class, Team::class))->toBe([
        'query_roles' => '(AsEnum<typeof Role> | null)[]',
        'local_roles' => '(AsEnum<typeof Role> | null)[]',
        'kept_query_roles' => '(AsEnum<typeof Role> | never[])[]',
        'kept_local_roles' => '(AsEnum<typeof Role> | never[])[]',
        'new_local_roles' => '(AsEnum<typeof Role> | null)[]',
        'kept_new_local_roles' => '(AsEnum<typeof Role> | never[])[]',
        'hinted_local_roles' => '(AsEnum<typeof Role> | null)[]',
        'collected_roles' => '(AsEnum<typeof Role> | null)[]',
        'chain_roles' => '(AsEnum<typeof Role> | null)[]',
        'nested_query_roles?' => '(AsEnum<typeof Role> | null)[]',
        'nested_local_roles?' => '(AsEnum<typeof Role> | null)[]',
        'nested_collected_roles?' => '(AsEnum<typeof Role> | null)[]',
        'narrowed_member_roles?' => '(AsEnum<typeof Role> | null)[] | null',
    ])
        ->and(json_decode((string) new MappedEnumColumnResource($team)->response(request())->getContent(), true))
        ->toBe(['data' => [
            'query_roles' => [null],
            'local_roles' => [null],
            'kept_query_roles' => [[]],
            'kept_local_roles' => [[]],
            'new_local_roles' => [null],
            'kept_new_local_roles' => [[]],
            'hinted_local_roles' => [null],
            'collected_roles' => [null],
            'chain_roles' => [null],
            'nested_query_roles' => [null],
            'nested_local_roles' => [null],
            'nested_collected_roles' => [null],
            'narrowed_member_roles' => [null],
        ]]);
});

// The column's nullability decides the null arm, not the closure the read sits in: a required column takes none.
it('keeps a nullable enum column\'s null arm inside a whenLoaded() closure, and adds none for a required one', function () {
    $item = (new OrderItem)->forceFill(['id' => 3]);
    $item->setRelation('order', (new Order)->forceFill(['id' => 7, 'status' => 0, 'payment_method' => null]));
    $pending = json_decode((string) json_encode(EnumResource::make(OrderStatus::Pending)), true);

    expect(publishedResourceKeyTypes(LoadedOrderEnumColumnResource::class, OrderItem::class))->toBe([
        'order_status?' => 'AsEnum<typeof OrderStatus>',
        'order_payment_method?' => 'AsEnum<typeof PaymentMethod> | null',
    ])
        ->and(json_decode((string) new LoadedOrderEnumColumnResource($item)->response(request())->getContent(), true)['data'])
        ->toBe(['order_status' => $pending, 'order_payment_method' => null]);
});

it('keeps a nullable enum column\'s null arm in a relation\'s arrayed map, and adds none for a required one', function () {
    $user = (new User)->forceFill(['id' => 1]);
    $user->setRelation('orders', new EloquentCollection([(new Order)->forceFill(['id' => 7, 'status' => 0, 'payment_method' => null])]));
    $pending = json_decode((string) json_encode(EnumResource::make(OrderStatus::Pending)), true);

    expect(publishedResourceKeyTypes(MappedOrderEnumColumnResource::class, User::class))->toBe([
        'order_statuses' => 'AsEnum<typeof OrderStatus>[]',
        'order_payment_methods' => '(AsEnum<typeof PaymentMethod> | null)[]',
        'loaded_order_statuses?' => 'AsEnum<typeof OrderStatus>[]',
        'loaded_order_payment_methods?' => '(AsEnum<typeof PaymentMethod> | null)[]',
    ])
        ->and(json_decode((string) new MappedOrderEnumColumnResource($user)->response(request())->getContent(), true)['data'])
        ->toBe([
            'order_statuses' => [$pending],
            'order_payment_methods' => [null],
            'loaded_order_statuses' => [$pending],
            'loaded_order_payment_methods' => [null],
        ]);
});

// A kept Collection sends the `[]` resolve() returns for a null role, and filter() never enters it.
it('publishes the workbench\'s relation-closure enum wraps with the null each sends', function () {
    $first = (new User)->forceFill(['id' => 1, 'name' => 'a', 'email' => 'a@example.com', 'role' => null]);
    $second = (new User)->forceFill(['id' => 2, 'name' => 'b', 'email' => 'b@example.com', 'role' => null]);
    $order = (new Order)->forceFill(['id' => 7]);
    $order->setRelation('user', $first);
    $team = (new Team)->forceFill(['id' => 9, 'week_days' => [WeekDays::Monday]]);
    $team->setRelation('members', new EloquentCollection([$first, $second]));
    $team->setRelation('owner', $first);
    $sends = fn (JsonResource $resource): array => json_decode((string) $resource->response(request())->getContent(), true)['data'];

    expect(publishedResourceKeyTypes(ConditionalParamEnumResource::class, Order::class)['user_role?'])
        ->toBe('AsEnum<typeof Role> | null')
        ->and($sends(new ConditionalParamEnumResource($order))['user_role'])->toBeNull()
        ->and(publishedResourceKeyTypes(EnumCollectionResource::class, Team::class)['members_via_var?'])
        ->toBe('(AsEnum<typeof Role> | never[])[]')
        ->and($sends(new EnumCollectionResource($team))['members_via_var'])->toBe([[], []])
        ->and(array_intersect_key(
            publishedResourceKeyTypes(RelationChainResource::class, Team::class),
            array_flip(['member_role_resources', 'member_role_resources_filtered', 'wrapped_filtered']),
        ))->toBe([
            'member_role_resources' => '(AsEnum<typeof Role> | never[])[]',
            'member_role_resources_filtered' => '(AsEnum<typeof Role> | never[])[] | Record<string, (AsEnum<typeof Role> | never[])>',
            'wrapped_filtered' => '{ roles: (AsEnum<typeof Role> | never[])[] | Record<string, (AsEnum<typeof Role> | never[])> }',
        ])
        ->and(array_intersect_key(
            $sends(new RelationChainResource($team)),
            array_flip(['member_role_resources', 'member_role_resources_filtered', 'wrapped_filtered']),
        ))->toBe([
            'member_role_resources' => [[], []],
            'member_role_resources_filtered' => ['1' => []],
            'wrapped_filtered' => ['roles' => ['1' => []]],
        ])
        ->and((string) new RelationChainResource($team)->response(request())->getContent())
        ->toContain('"member_mapped_fcc":{},"member_plucked_fcc":{}');
});

/**
 * What Laravel sends for KeptEnumMapResource over a team whose second member has no role, nor its owner's second post a
 * priority.
 *
 * @return array<string, mixed>
 */
function keptEnumMapSends(): array
{
    $posts = new EloquentCollection([
        (new Post)->forceFill(['id' => 1, 'priority' => Priority::High]),
        (new Post)->forceFill(['id' => 2, 'priority' => null]),
    ]);
    $team = (new Team)->forceFill(['id' => 9]);
    $team->setRelation('members', new EloquentCollection([
        (new User)->forceFill(['id' => 1, 'role' => Role::Admin])->setRelation('posts', $posts),
        (new User)->forceFill(['id' => 7, 'role' => null])->setRelation('posts', new EloquentCollection),
    ]));
    $team->setRelation('owner', (new User)->forceFill(['id' => 1])->setRelation('posts', $posts));

    return json_decode((string) new KeptEnumMapResource($team)->response(request())->getContent(), true)['data'];
}

// A wrap a kept Collection serializes sends the `[]` resolve() returns for a null enum, as on a page. jsonSerialize()
// sends it too but null for a wrap in an array, and toArray() turns an inner map into an array filter() enters, so
// both stay unknown there (docs/known-gaps.md).
it('publishes the [] a null enum sends from an EnumResource map kept as a Collection', function () {
    $admin = json_decode((string) json_encode(EnumResource::make(Role::Admin)), true);
    $high = json_decode((string) json_encode(EnumResource::make(Priority::High)), true);
    $published = json_decode((string) json_encode(EnumResource::make(Status::Published)), true);

    expect(publishedResourceKeyTypes(KeptEnumMapResource::class, Team::class))->toBe([
        'values_kept' => '(AsEnum<typeof Role> | never[])[]',
        'filter_map_kept' => '(AsEnum<typeof Role> | never[])[] | Record<string, (AsEnum<typeof Role> | never[])>',
        'filter_map_all' => '(AsEnum<typeof Role> | null)[] | Record<string, (AsEnum<typeof Role> | null)>',
        'untyped_kept' => '(AsEnum<typeof Role> | never[])[]',
        'whenloaded_kept?' => '(AsEnum<typeof Role> | never[])[]',
        'collect_kept' => '(AsEnum<typeof Role> | never[])[]',
        'backed_kept' => '(AsEnum<typeof Priority> | never[])[]',
        'backed_all' => '(AsEnum<typeof Priority> | null)[]',
        'to_array' => '(AsEnum<typeof Role> | null)[]',
        'json_serialized' => 'unknown',
        'nested_all' => '(AsEnum<typeof Priority> | never[])[][]',
        'nested_to_array' => 'unknown',
        'nested_collect_to_array' => 'unknown',
        'nested_loaded_to_array?' => 'unknown',
        'tried_kept' => '(AsEnum<typeof Status> | never[])[]',
        'tried_all' => '(AsEnum<typeof Status> | null)[]',
    ])->and(keptEnumMapSends())->toBe([
        'values_kept' => [$admin, []],
        'filter_map_kept' => [$admin, []],
        'filter_map_all' => [$admin, null],
        'untyped_kept' => [$admin, []],
        'whenloaded_kept' => [$admin, []],
        'collect_kept' => [$admin, []],
        'backed_kept' => [$high, []],
        'backed_all' => [$high, null],
        'to_array' => [$admin, null],
        'json_serialized' => [$admin, []],
        'nested_all' => [[$high, []], []],
        'nested_to_array' => [[$high, null], []],
        'nested_collect_to_array' => [[$high, null], []],
        'nested_loaded_to_array' => [[$high, null], []],
        'tried_kept' => [$published, []],
        'tried_all' => [$published, null],
    ]);
});

// A `$this` read and an array element take the same rule as a mapped element's own wrap; filter() reaches a helper's
// wrap on another class just as the resource's own.
it('publishes the [] a kept map sends for a wrap of the subject\'s own null enum or one inside an array', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = fn (string $query): array => json_decode(
        (string) new KeptOwnRoleMapResource($user)->response(Request::create('/'.$query))->getContent(),
        true,
    )['data'];
    $published = json_decode((string) json_encode(EnumResource::make(Status::Published)), true);

    expect(publishedResourceKeyTypes(KeptOwnRoleMapResource::class, User::class))->toBe([
        'own_role_kept' => '(AsEnum<typeof Role> | never[])[]',
        'own_role_all' => '(AsEnum<typeof Role> | null)[]',
        'element_kept' => '{ role: AsEnum<typeof Role> | never[] }[]',
        'element_all' => '{ role: AsEnum<typeof Role> | null }[]',
        'tried' => 'AsEnum<typeof Status> | null',
        'from' => 'AsEnum<typeof Status>',
        'helper_tried' => 'AsEnum<typeof Status> | null',
    ])
        ->and($sent('?status=7'))->toBe([
            'own_role_kept' => [[]],
            'own_role_all' => [null],
            'element_kept' => [['role' => []]],
            'element_all' => [['role' => null]],
            'tried' => null,
            'from' => $published,
            'helper_tried' => null,
        ])
        ->and($sent('?status=1')['tried'])->toBe($published);
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

// The bare read sends the whole model, so the subclass a narrowing proves is what it publishes.
it('publishes a model-bound variable read bare under an instanceof test as the subclass it narrows to', function () {
    $draft = (new DraftPost)->forceFill(['id' => 4, 'title' => 'abc']);
    $comment = (new Comment)->forceFill(['id' => 9]);
    $sent = fn (Post $post): string => (string) new GuardedDraftCommentResource($comment->setRelation('post', $post))
        ->response(request())->getContent();

    expect(publishedResourceKeyTypes(GuardedDraftCommentResource::class, Comment::class))->toBe([
        'guarded_draft?' => '{ p: DraftPost } | null',
        'narrowed_draft?' => 'DraftPost | null',
    ])
        ->and($sent($draft))->toBe('{"data":{"guarded_draft":{"p":{"id":4,"title":"abc"}},"narrowed_draft":{"id":4,"title":"abc"}}}')
        ->and($sent((new Post)->forceFill(['id' => 5, 'title' => 'abc'])))->toBe('{"data":{"guarded_draft":null,"narrowed_draft":null}}');
});

// The loop assigns its value before each pass, never between the test and a read the pass makes.
it('narrows a foreach value variable by a guard or a positive test in the loop body', function () {
    $user = (new User)->forceFill(['id' => 1]);
    $sent = fn (Post $post): string => (string) new GuardedLoopPostsResource($user->setRelation('posts', new EloquentCollection([$post])))
        ->response(request())->getContent();

    expect(publishedResourceKeyTypes(GuardedLoopPostsResource::class, User::class))->toBe([
        'guarded_word_count?' => 'string | null',
        'positive_word_count?' => 'string | null',
    ])
        ->and($sent((new DraftPost)->forceFill(['word_count' => 12])))
        ->toBe('{"data":{"guarded_word_count":"12","positive_word_count":"12"}}')
        ->and($sent((new DraftPost)->forceFill(['word_count' => null])))
        ->toBe('{"data":{"guarded_word_count":null,"positive_word_count":null}}')
        ->and($sent((new Post)->forceFill(['word_count' => 12])))->toBe('{"data":[]}');
});

// A value that is both an interface and a class it proves is best named by the class, in whichever order it is proven.
it('reads a value proven to be an interface and a class that does not implement it as the class', function () {
    $analysis = resolve(AstEngine::class)->analyze(InterfaceGuardedResource::class, 'toArray', null);
    $types = array_column($analysis->properties, 'type', 'name');

    expect($types)->toBe([
        'interface_then_class' => 'string',
        'class_then_interface' => 'string',
        'declared_interface' => 'string',
    ])
        ->and(new InterfaceGuardedResource((new CountedPost)->forceFill(['title' => 'abc']))->response(request())->getContent())
        ->toBe('{"data":{"interface_then_class":"abc","class_then_interface":"abc","declared_interface":"abc"}}');
});

// A test for a class the binding already is proves nothing narrower, so the bound model and collection still type. A
// collection's toArray() reads as it does untested: each comment's columns, never the Comment interface's accessors.
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
        'narrowed_spread?' => 'Record<string, unknown>[] | null',
    ])->and($sent['data'])->toMatchArray([
        'model_guarded_title' => 'First',
        'model_guarded_word_count' => 12,
        'interface_guarded_title' => 'First',
        'model_ternary_title' => 'First',
        'spread_title' => 'First',
        'spread_publishable' => false,
        'narrowed_contents' => ['alpha', 'beta'],
    ])->and(array_column($sent['data']['narrowed_spread'], 'content'))->toBe(['alpha', 'beta'])
        ->and(array_keys($sent['data']['narrowed_spread'][0]))->not->toContain('preview')->not->toContain('post');
});

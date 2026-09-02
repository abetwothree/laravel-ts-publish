<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergeArrayMergeChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergeSpreadChildResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UnreadableReturnResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Illuminate\Notifications\DatabaseNotification;
use Workbench\Accounting\Http\Resources\InvoiceResource;
use Workbench\Accounting\Models\Invoice;
use Workbench\Accounting\Models\Payment;
use Workbench\App\Enums\OrderStatus;
use Workbench\App\Enums\PaymentMethod;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Enums\WeekDays;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\Admin\Store as AdminStore;
use Workbench\App\Http\Resources\Admin\StoreCollection as AdminStoreCollection;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\AttachmentCollection;
use Workbench\App\Http\Resources\AttachmentResource;
use Workbench\App\Http\Resources\BareFuncCallResource;
use Workbench\App\Http\Resources\BareMethodReturnResource;
use Workbench\App\Http\Resources\BodylessTeamResource;
use Workbench\App\Http\Resources\BooleanExprResource;
use Workbench\App\Http\Resources\BranchedInlineFqcnResource;
use Workbench\App\Http\Resources\CaseSpreadResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\ChildSharedResource;
use Workbench\App\Http\Resources\ClassConstantResource;
use Workbench\App\Http\Resources\ClosureControlFlowResource;
use Workbench\App\Http\Resources\ClosureParamShadowResource;
use Workbench\App\Http\Resources\ClosureUnionMetadataResource;
use Workbench\App\Http\Resources\CoalesceChannelResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\CommonResource;
use Workbench\App\Http\Resources\ConditionalDefaultsResource;
use Workbench\App\Http\Resources\ConditionalParamArrayResource;
use Workbench\App\Http\Resources\ConditionalParamEnumResource;
use Workbench\App\Http\Resources\ConditionalParamFullClosureResource;
use Workbench\App\Http\Resources\ConditionalParamMappedResource;
use Workbench\App\Http\Resources\ConditionalParamPrimitiveResource;
use Workbench\App\Http\Resources\ControlFlowReturnResource;
use Workbench\App\Http\Resources\CustomImportChannelResource;
use Workbench\App\Http\Resources\DelegatingResource;
use Workbench\App\Http\Resources\DelegatingWithMixinResource;
use Workbench\App\Http\Resources\EmptyResource;
use Workbench\App\Http\Resources\EmptyWithMixinResource;
use Workbench\App\Http\Resources\EnumCollectionResource;
use Workbench\App\Http\Resources\EnumNullFirstResource;
use Workbench\App\Http\Resources\EventLogResource;
use Workbench\App\Http\Resources\ExtendedAddressResource;
use Workbench\App\Http\Resources\FluentSelfResource;
use Workbench\App\Http\Resources\GuardClauseClosureResource;
use Workbench\App\Http\Resources\HelperCallResource;
use Workbench\App\Http\Resources\InlineArrayFqcnResource;
use Workbench\App\Http\Resources\Ledger;
use Workbench\App\Http\Resources\LedgerCollection;
use Workbench\App\Http\Resources\LedgerResource;
use Workbench\App\Http\Resources\LocalVarGuardClauseResource;
use Workbench\App\Http\Resources\LocalVarReassignResource;
use Workbench\App\Http\Resources\LocalVarRecursionResource;
use Workbench\App\Http\Resources\LocalVarResource;
use Workbench\App\Http\Resources\LocalVarSpreadResource;
use Workbench\App\Http\Resources\LoopReturnResource;
use Workbench\App\Http\Resources\MapRelationFilterResource;
use Workbench\App\Http\Resources\MediaTypeInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypePositiveInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypeResource;
use Workbench\App\Http\Resources\MediaTypeUnknownResource;
use Workbench\App\Http\Resources\MerchantResource;
use Workbench\App\Http\Resources\MergeClosureResource;
use Workbench\App\Http\Resources\MergeMultiBranchClosureResource;
use Workbench\App\Http\Resources\MiscCollection;
use Workbench\App\Http\Resources\ModelWrappedPropResource;
use Workbench\App\Http\Resources\MutuallyRecursiveSpreadResource;
use Workbench\App\Http\Resources\NestedResourceSpreadResource;
use Workbench\App\Http\Resources\NonArrayReturnResource;
use Workbench\App\Http\Resources\NonThisReceiverSpreadResource;
use Workbench\App\Http\Resources\OrderClosureResource;
use Workbench\App\Http\Resources\OrderCollection;
use Workbench\App\Http\Resources\OrderCountsResource;
use Workbench\App\Http\Resources\OrderDetailResource;
use Workbench\App\Http\Resources\OrderExceptResource;
use Workbench\App\Http\Resources\OrderFilterEdgeResource;
use Workbench\App\Http\Resources\OrderItemResource;
use Workbench\App\Http\Resources\OrderOnlyResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\OrderSummaryResource;
use Workbench\App\Http\Resources\PostAttachmentFilterResource;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PreserveKeysCollection;
use Workbench\App\Http\Resources\PreserveKeysPropertyCollection;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Http\Resources\QuirkyResource;
use Workbench\App\Http\Resources\ReflectedMethodChannelResource;
use Workbench\App\Http\Resources\Registrar as BareRegistrarResource;
use Workbench\App\Http\Resources\RegistrarResource;
use Workbench\App\Http\Resources\RelationChainResource;
use Workbench\App\Http\Resources\ResourceWrappedEnumResource;
use Workbench\App\Http\Resources\ShadowedClosureParamResource;
use Workbench\App\Http\Resources\SpreadJsonBaseResource;
use Workbench\App\Http\Resources\SpreadWithClosureResource;
use Workbench\App\Http\Resources\SpreadWithGuardClauseClosureResource;
use Workbench\App\Http\Resources\SpreadWithGuardDoubleClosureReturnResource;
use Workbench\App\Http\Resources\StaticCallResource;
use Workbench\App\Http\Resources\SupplierResource;
use Workbench\App\Http\Resources\SupplierSummaryCollection;
use Workbench\App\Http\Resources\SupplierSummaryResource;
use Workbench\App\Http\Resources\TagResource;
use Workbench\App\Http\Resources\TeamMemberResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Http\Resources\TernaryResource;
use Workbench\App\Http\Resources\ToArrayCastsResource;
use Workbench\App\Http\Resources\TrackingEventResource as AppTrackingEventResource;
use Workbench\App\Http\Resources\TraitSpreadCoverageResource;
use Workbench\App\Http\Resources\UnitEnumResource;
use Workbench\App\Http\Resources\UserCollection;
use Workbench\App\Http\Resources\UserExceptResource;
use Workbench\App\Http\Resources\UserOnlyHiddenResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\VarReturnSpreadResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Merchant;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Tag;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Models\UuidPost;
use Workbench\App\Models\Warehouse;
use Workbench\Blog\Enums\ArticleStatus;
use Workbench\Blog\Enums\ContentType;
use Workbench\Blog\Http\Resources\ApiArticleResource;
use Workbench\Blog\Http\Resources\ReactionResource;
use Workbench\Blog\Models\Article;
use Workbench\Blog\Models\Reaction;
use Workbench\Crm\Http\Resources\DealResource;
use Workbench\Crm\Models\Deal;
use Workbench\Crm\Models\User as CrmUser;
use Workbench\Shipping\Http\Resources\ShipmentResource;
use Workbench\Shipping\Http\Resources\TrackingEventResource;
use Workbench\Shipping\Models\Shipment;
use Workbench\Shipping\Models\TrackingEvent;

describe('ResourceAstAnalyzer with PostResource', function () {
    test('extracts properties from toArray return array', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('id', 'title', 'content', 'status', 'status_new', 'visibility', 'visibility_new', 'priority', 'priority_new');
    });

    test('identifies EnumResource::make calls', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        expect($analysis->enumResources)
            ->toHaveKey('status')
            ->toHaveKey('status_new')
            ->toHaveKey('visibility')
            ->toHaveKey('visibility_new')
            ->toHaveKey('priority')
            ->toHaveKey('priority_new');
    });

    test('hasMany relation with only() produces array type with [] suffix', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $comments = collect($analysis->properties)->firstWhere('name', 'comments');

        expect($comments)->not->toBeNull()
            ->and($comments['type'])->toEndWith('[]');
    });

    test('hasMany relation with only() includes relation keys in inline type', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $comments = collect($analysis->properties)->firstWhere('name', 'comments');

        expect($comments['type'])->toContain('id: number')
            ->and($comments['type'])->toContain('content: string')
            ->and($comments['type'])->toContain('user: User');
    });

    // cast, mixin method, and resolve() expressions ————————————

    test('(bool) cast resolves to boolean', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'published');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('boolean')
            ->and($prop['optional'])->toBeFalse();
    });

    test('(int) cast inside arithmetic expression resolves to number', function () {
        // `(int) round(...) / 2` — cast binds tighter than /, outer node is BinaryOp\Div
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'rating_display');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('number')
            ->and($prop['optional'])->toBeFalse();
    });

    test('(string) cast resolves to string', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'word_count');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });

    test('(array) cast of an inline array literal preserves its shape', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'heading_content');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ title: string; summary: string }')
            ->and($prop['optional'])->toBeFalse();
    });

    test('@mixin method with return type — publishable resolves to boolean', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'publishable');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('boolean')
            ->and($prop['optional'])->toBeFalse();
    });

    test('@mixin method via $this->resource — comments_count resolves to number', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'comments_count');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('number')
            ->and($prop['optional'])->toBeFalse();
    });

    test('@mixin method with docblock only — is_featured resolves to boolean', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'is_featured');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('boolean')
            ->and($prop['optional'])->toBeFalse();
    });

    test('nullsafe relation method in whenLoaded closure — category_is_first resolves to boolean|null', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'category_is_first');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('boolean | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('nullsafe relation method via $this->resource in whenLoaded closure — category_is_active resolves to boolean|null', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'category_is_active');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('boolean | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('resource collection with ->resolve() — comments_resolved resolves to CommentResource[]', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'comments_resolved');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CommentResource[]')
            ->and($prop['optional'])->toBeTrue();
    });

    // static method call expressions ———————————————————————————————

    test('$this::staticMethod() resolves return type — post_class_name', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'post_class_name');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });

    test('$this->resource::staticMethod() resolves return type — post_table_name', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'post_table_name');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });

    test('relation::staticMethod() in whenLoaded closure resolves return type — category_class_name', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'category_class_name');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    test('resource->relation::staticMethod() in whenLoaded closure resolves return type — category_table_name', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'category_table_name');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with UserResource', function () {
    test('identifies nested resource collection', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        expect($analysis->nestedResources)->toHaveKey('posts');
    });

    test('resolves whenLoaded bare relation as model FQCN', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)->toHaveKey('profile');
    });

    test('marks whenHas as optional', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $phone = collect($analysis->properties)->firstWhere('name', 'phone');

        expect($phone['optional'])->toBeTrue();
    });

    test('marks whenNotNull as optional', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $avatar = collect($analysis->properties)->firstWhere('name', 'avatar');

        expect($avatar['optional'])->toBeTrue();
    });

    test('marks whenCounted as optional number', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $postsCount = collect($analysis->properties)->firstWhere('name', 'posts_count');

        expect($postsCount['type'])->toBe('number')
            ->and($postsCount['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with OrderResource', function () {
    test('resolves mergeWhen properties as optional', function () {
        $reflection = new ReflectionClass(OrderResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Order::class);
        $analysis = $analyzer->analyze();

        $shippedAt = collect($analysis->properties)->firstWhere('name', 'shipped_at');
        $deliveredAt = collect($analysis->properties)->firstWhere('name', 'delivered_at');

        expect($shippedAt['optional'])->toBeTrue()
            ->and($deliveredAt['optional'])->toBeTrue();
    });

    test('marks whenAggregated as optional number', function () {
        $reflection = new ReflectionClass(OrderResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Order::class);
        $analysis = $analyzer->analyze();

        $totalAvg = collect($analysis->properties)->firstWhere('name', 'total_avg');

        expect($totalAvg['type'])->toBe('number')
            ->and($totalAvg['optional'])->toBeTrue();
    });

    test('marks when() as optional', function () {
        $reflection = new ReflectionClass(OrderResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Order::class);
        $analysis = $analyzer->analyze();

        $paidAt = collect($analysis->properties)->firstWhere('name', 'paid_at');

        expect($paidAt['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with CommentResource', function () {
    test('resolves nested resource make with conditional argument', function () {
        $reflection = new ReflectionClass(CommentResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        expect($analysis->nestedResources)->toHaveKey('author')
            ->and($analysis->nestedResources)->toHaveKey('post');

        $author = collect($analysis->properties)->firstWhere('name', 'author');
        $post = collect($analysis->properties)->firstWhere('name', 'post');

        expect($author['optional'])->toBeTrue()
            ->and($post['optional'])->toBeTrue();
    });

    test('resolves new Resource() instantiation as nested resource', function () {
        $reflection = new ReflectionClass(CommentResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        $authorNew = collect($analysis->properties)->firstWhere('name', 'author_new');
        $postNew = collect($analysis->properties)->firstWhere('name', 'post_new');

        expect($authorNew)->not->toBeNull()
            ->and($authorNew['type'])->toBe('UserResource')
            ->and($authorNew['optional'])->toBeTrue()
            ->and($postNew)->not->toBeNull()
            ->and($postNew['type'])->toBe('PostResource')
            ->and($postNew['optional'])->toBeTrue();
    });

    test('tracks new Resource() FQCNs in nestedResources', function () {
        $reflection = new ReflectionClass(CommentResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        expect($analysis->nestedResources)->toHaveKey('author_new')
            ->and($analysis->nestedResources['author_new'])->toBe(UserResource::class)
            ->and($analysis->nestedResources)->toHaveKey('post_new')
            ->and($analysis->nestedResources['post_new'])->toBe(PostResource::class);
    });

    test('resolves non-conditional new Resource() as non-optional', function () {
        $reflection = new ReflectionClass(CommentResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        $postDirect = collect($analysis->properties)->firstWhere('name', 'post_direct');

        expect($postDirect)->not->toBeNull()
            ->and($postDirect['type'])->toBe('PostResource')
            ->and($postDirect['optional'])->toBeFalse()
            ->and($analysis->nestedResources)->toHaveKey('post_direct')
            ->and($analysis->nestedResources['post_direct'])->toBe(PostResource::class);
    });
});

describe('ResourceAstAnalyzer with TeamMemberResource', function () {
    test('marks whenPivotLoaded as optional unknown', function () {
        $reflection = new ReflectionClass(TeamMemberResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $teamRole = collect($analysis->properties)->firstWhere('name', 'team_role');
        $joinedAt = collect($analysis->properties)->firstWhere('name', 'joined_at');

        expect($teamRole['type'])->toBe('unknown')
            ->and($teamRole['optional'])->toBeTrue()
            ->and($joinedAt['type'])->toBe('unknown')
            ->and($joinedAt['optional'])->toBeTrue();
    });

    test('marks whenPivotLoadedAs as optional unknown', function () {
        $reflection = new ReflectionClass(TeamMemberResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $subscriptionRole = collect($analysis->properties)->firstWhere('name', 'subscription_role');

        expect($subscriptionRole['type'])->toBe('unknown')
            ->and($subscriptionRole['optional'])->toBeTrue();
    });

    test('resolves whenHas as optional', function () {
        $reflection = new ReflectionClass(TeamMemberResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $role = collect($analysis->properties)->firstWhere('name', 'role');
        $membershipLevel = collect($analysis->properties)->firstWhere('name', 'membership_level');

        expect($role['optional'])->toBeTrue()
            ->and($membershipLevel['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with TeamResource', function () {
    test('resolves Resource::make with whenLoaded conditional argument', function () {
        $reflection = new ReflectionClass(TeamResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Team::class);
        $analysis = $analyzer->analyze();

        $owner = collect($analysis->properties)->firstWhere('name', 'owner');

        expect($owner['type'])->toBe('UserResource')
            ->and($owner['optional'])->toBeTrue()
            ->and($analysis->nestedResources)->toHaveKey('owner');
    });

    test('resolves Resource::collection with whenLoaded conditional argument', function () {
        $reflection = new ReflectionClass(TeamResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Team::class);
        $analysis = $analyzer->analyze();

        $members = collect($analysis->properties)->firstWhere('name', 'members');

        expect($members['type'])->toBe('TeamMemberResource[]')
            ->and($members['optional'])->toBeTrue()
            ->and($analysis->nestedResources)->toHaveKey('members');
    });

    test('resolves mergeWhen with array properties', function () {
        $reflection = new ReflectionClass(TeamResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Team::class);
        $analysis = $analyzer->analyze();

        $settings = collect($analysis->properties)->firstWhere('name', 'settings');

        expect($settings)->not->toBeNull()
            ->and($settings['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with a body-less subclass', function () {
    test('a resource with no toArray() of its own inherits the nearest ancestor that has one', function () {
        $analysis = (new ResourceAstAnalyzer(
            new ReflectionClass(BodylessTeamResource::class),
            Team::class,
        ))->analyze();

        // `created_at` pins the shape as the ancestor's toArray(), not the model-delegated column set,
        // which yields `id`/`name` too and would leave this test vacuous.
        expect(array_column($analysis->properties, 'name'))->toContain('id', 'name')
            ->not->toContain('created_at');
    });

    test('the inherited shape carries the ancestor nested resources, not just scalar columns', function () {
        $analysis = (new ResourceAstAnalyzer(
            new ReflectionClass(BodylessTeamResource::class),
            Team::class,
        ))->analyze();

        $members = collect($analysis->properties)->firstWhere('name', 'members');

        expect($members['type'])->toBe('TeamMemberResource[]')
            ->and($analysis->nestedResources)->toHaveKey('owner');
    });

    // Outcome guard only: with no model passed the inherited analysis is already empty, so returning it or
    // falling through both give []. The `properties !== []` conjunct is pinned by the body-less *collection*
    // fixtures that need the fall-through to reach buildCollectionDelegatedAnalysis(); dropping it fails 9.
    test('an ancestor chain with no toArray() anywhere stays empty rather than borrowing a shape', function () {
        $analysis = (new ResourceAstAnalyzer(new ReflectionClass(ChildSharedResource::class)))->analyze();

        expect($analysis->properties)->toBe([]);
    });
});

describe('ResourceAstAnalyzer with RelationChainResource (relation-rooted collection chains)', function () {
    beforeEach(function () {
        $this->analysis = (new ResourceAstAnalyzer(new ReflectionClass(RelationChainResource::class), Team::class))
            ->analyze();
        $this->props = collect($this->analysis->properties)->keyBy('name');
    });

    test('identity-only chain preserves the relation element type', function () {
        expect($this->props['first_members']['type'])->toBe('User[]')
            ->and($this->props['first_members']['optional'])->toBeFalse()
            ->and($this->analysis->modelFqcns)->toHaveKey('first_members')
            ->and($this->analysis->modelFqcns['first_members'])->toBe(User::class);
    });

    test('map() closure with an inline array body produces an inline object array', function () {
        expect($this->props['member_cards']['type'])->toBe('{ id: number; name: string }[]')
            ->and($this->props['member_cards']['optional'])->toBeFalse();
    });

    test('map() closure body embeds both an enum-cast column and a model-backed property, and both propagate', function () {
        // role is a nullable column, so the body type is a union and gets parenthesized before '[]'.
        expect($this->props['member_profiles']['type'])->toBe('({ id: number; role: RoleType | null; owner: User })[]')
            ->and($this->analysis->inlineEnumFqcns)->toHaveKey('member_profiles')
            ->and($this->analysis->inlineEnumFqcns['member_profiles'])->toContain(Role::class)
            ->and($this->analysis->inlineModelFqcns)->toHaveKey('member_profiles')
            ->and($this->analysis->inlineModelFqcns['member_profiles'])->toContain(User::class);
    });

    test('->map->only() reached directly off the relation resolves the HigherOrderCollectionProxy', function () {
        // arrayWrapType() parenthesizes on any '|', including one nested inside the braces — same
        // as member_profiles above, not a defect specific to this proxy.
        expect($this->props['member_map_only']['type'])->toBe('({ id: number; role: RoleType | null })[]')
            ->and($this->props['member_map_only']['optional'])->toBeFalse()
            ->and($this->analysis->inlineEnumFqcns)->toHaveKey('member_map_only')
            ->and($this->analysis->inlineEnumFqcns['member_map_only'])->toContain(Role::class);
    });

    test('pluck() after the relation root resolves to the column type, array-wrapped', function () {
        expect($this->props['member_emails']['type'])->toBe('string[]')
            ->and($this->props['member_emails']['optional'])->toBeFalse();
    });

    // TypeScript parses 'RoleType | null[]' as RoleType | (null[]), so the union must be parenthesized.
    test('pluck() on a nullable column parenthesizes the union before the array suffix', function () {
        expect($this->props['member_roles']['type'])->toBe('(RoleType | null)[]')
            ->and($this->props['member_roles']['optional'])->toBeFalse();
    });

    // Must stay 'enumFqcn'-tagged (not demoted): the transformer's AsEnum rewrite needs that
    // channel to render the wrapped-object array as AsEnum<typeof Role>[], not plain RoleType[].
    test('map() body that is entirely EnumResource::make() stays an array and is enumFqcn-tagged', function () {
        expect($this->props['member_role_resources']['type'])->toBe('RoleType[]')
            ->and($this->props['member_role_resources']['optional'])->toBeFalse()
            ->and($this->analysis->enumResources)->toHaveKey('member_role_resources')
            ->and($this->analysis->enumResources['member_role_resources'])->toBe(Role::class)
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('member_role_resources');
    });

    // filter() clears sequential keys, so the map body ends up keyedObjectArm()-wrapped. That shape
    // stays 'enumFqcn'-tagged (not demoted): the transformer's substitution-based rewrite reproduces
    // it losslessly, AsEnum-wrapping both the array arm and the keyed Record arm.
    test('filter() before an EnumResource::make() map body stays enumFqcn-tagged', function () {
        expect($this->props['member_role_resources_filtered']['type'])
            ->toBe('RoleType[] | Record<string, RoleType>')
            ->and($this->analysis->enumResources)->toHaveKey('member_role_resources_filtered')
            ->and($this->analysis->enumResources['member_role_resources_filtered'])->toBe(Role::class)
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('member_role_resources_filtered');
    });

    // Same keyed-Record shape, nested inside an inline array this time: analyzeInlineArray()
    // substitutes the bare RoleType token in place, so both union arms come out AsEnum-wrapped —
    // matching member_role_resources_filtered above, whose PHP shape is identical.
    test('an EnumResource-wrapped enum inside an inline array literal is substituted, not rebuilt', function () {
        expect($this->props['wrapped_filtered']['type'])
            ->toBe('{ roles: AsEnum<typeof Role>[] | Record<string, AsEnum<typeof Role>> }');
    });

    // A string ('strtoupper') or array ([$this, 'method']) callable has no closure body to analyze.
    test('map() with a non-closure callable argument stays unknown', function () {
        expect($this->props['member_names_upper']['type'])->toBe('unknown')
            ->and($this->props['member_formatted']['type'])->toBe('unknown');
    });

    // PHP-Parser's CallLike::getArgs() asserts !isFirstClassCallable(), so under zend.assertions=1
    // it throws AssertionError instead of returning []; `->map(...)` must be detected before that.
    test('map()/pluck() as a first-class callable degrades to unknown instead of throwing', function () {
        expect($this->props['member_mapped_fcc']['type'])->toBe('unknown')
            ->and($this->props['member_plucked_fcc']['type'])->toBe('unknown');
    });

    test('first() as the outermost op yields the element type or null', function () {
        expect($this->props['first_member']['type'])->toBe('User | null')
            ->and($this->props['first_member']['optional'])->toBeFalse();
    });

    // load()/loadMissing() are identity ops: they don't break sequential keys, and don't block
    // the first()/last() terminal recognition that walks past them.
    test('load() is an identity op that preserves sequential keys and the first() terminal', function () {
        expect($this->props['members_after_load']['type'])->toBe('User[]')
            ->and($this->props['first_member_after_load']['type'])->toBe('User | null');
    });

    // A Collection whose keys are gapped or reordered json_encodes to an object, not an array.
    test('a key-preserving chain with no values() carries the object arm', function () {
        expect($this->props['members_sorted']['type'])->toBe('User[] | Record<string, User>')
            ->and($this->props['members_filtered_cards']['type'])
            ->toBe('{ id: number }[] | Record<string, { id: number }>')
            ->and($this->props['members_tail']['type'])->toBe('User[] | Record<string, User>')
            ->and($this->props['members_sliced_emails']['type'])->toBe('string[] | Record<string, string>')
            ->and($this->props['members_keyed_by_id']['type'])->toBe('string[] | Record<string, string>');
    });

    test('values() at the end of a key-preserving chain restores the plain array type', function () {
        expect($this->props['members_skipped']['type'])->toBe('User[]')
            ->and($this->analysis->modelFqcns['members_skipped'])->toBe(User::class);
    });
});

describe('ResourceAstAnalyzer with EnumCollectionResource (EnumResource::collection() shapes)', function () {
    beforeEach(function () {
        $this->analysis = (new ResourceAstAnalyzer(new ReflectionClass(EnumCollectionResource::class), Team::class))
            ->analyze();
        $this->props = collect($this->analysis->properties)->keyBy('name');
    });

    test('accessor-backed list<Enum> stays enumFqcn-tagged and array-shaped', function () {
        expect($this->props['status_history']['type'])->toBe('StatusType[]')
            ->and($this->analysis->enumResources)->toHaveKey('status_history')
            ->and($this->analysis->enumResources['status_history'])->toBe(Status::class)
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('status_history');
    });

    test('AsEnumCollection cast stays enumFqcn-tagged, array-shaped, and nullable', function () {
        expect($this->props['week_days']['type'])->toBe('WeekDaysType[] | null')
            ->and($this->analysis->enumResources)->toHaveKey('week_days')
            ->and($this->analysis->enumResources['week_days'])->toBe(WeekDays::class);
    });

    // analyzeInlineArray() computes its own AsEnum rewrite eagerly, so the [] survives here even
    // though the top-level properties above stay as their bare, un-rewritten element-array type
    // until ResourceTransformer::rewriteEnumResourceTypes() runs.
    test('EnumResource::collection() inside an inline array keeps its [] suffix', function () {
        expect($this->props['wrapped_week_days']['type'])
            ->toBe('{ week_days: AsEnum<typeof WeekDays>[] | null }');
    });

    // whenHas() never resolves its value argument's own type — the attribute supplies type and
    // array-ness — but IS checked for EnumResource::make()/::collection() shape (isEnumResourceWrapCall()),
    // so this first-class-callable value still promotes to the 'enumFqcn' (wrapped) channel.
    test('first-class callable inside whenHas() promotes to the enumFqcn (wrapped) channel', function () {
        expect($this->props['week_days_when_has']['type'])->toBe('WeekDaysType[] | null')
            ->and($this->analysis->enumResources)->toHaveKey('week_days_when_has')
            ->and($this->analysis->enumResources['week_days_when_has'])->toBe(WeekDays::class)
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('week_days_when_has');
    });

    // whenAppended() applies the identical check, for an ordinary (non-FCC) EnumResource::collection()
    // value — whenAppended() never forwards the attribute value to a Closure, so only this eagerly-
    // evaluated form is realistically reachable there.
    test('EnumResource::collection() value inside whenAppended() promotes to the enumFqcn channel', function () {
        expect($this->props['status_history_when_appended']['type'])->toBe('StatusType[]')
            ->and($this->analysis->enumResources)->toHaveKey('status_history_when_appended')
            ->and($this->analysis->enumResources['status_history_when_appended'])->toBe(Status::class)
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('status_history_when_appended');
    });

    // An explicit default arm unions in a 'string' type. The 'enumFqcn' channel stays live (not
    // demoted): the transformer's substitution-based rewrite reproduces the full union losslessly.
    test('whenHas() with an explicit default stays enumFqcn-tagged and keeps the full union', function () {
        expect($this->props['week_days_when_has_default']['type'])
            ->toBe('WeekDaysType[] | null | string')
            ->and($this->props['week_days_when_has_default']['optional'])->toBeFalse()
            ->and($this->analysis->enumResources)->toHaveKey('week_days_when_has_default')
            ->and($this->analysis->enumResources['week_days_when_has_default'])->toBe(WeekDays::class)
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('week_days_when_has_default');
    });

    // $variable->map() (not $this->relation->map()) with a body entirely EnumResource::make(...)
    // must stay array-shaped and enumFqcn-tagged, the same contract as the relation-chain case.
    test('local variable ->map() with an EnumResource::make() body stays array-shaped and enumFqcn-tagged', function () {
        expect($this->props['members_via_var']['type'])->toBe('RoleType[]')
            ->and($this->analysis->enumResources)->toHaveKey('members_via_var')
            ->and($this->analysis->enumResources['members_via_var'])->toBe(Role::class);
    });

    // A bare (unwrapped) enum read nested inside an inline array registers in inlineEnumFqcns,
    // a channel distinct from enumResources/directEnumFqcns that ResourceTransformer reads
    // separately (see propertyInlineEnumFqcns in rewriteEnumResourceTypes()'s import-GC).
    test('a bare enum read nested inside an inline array registers in inlineEnumFqcns', function () {
        expect($this->props['member_role_snapshot']['type'])->toBe('({ role: RoleType | null })[]')
            ->and($this->analysis->inlineEnumFqcns)->toHaveKey('member_role_snapshot')
            ->and($this->analysis->inlineEnumFqcns['member_role_snapshot'])->toContain(Role::class);
    });

    // A mixed ternary nested in an inline array whose arms differ in shape (an array-forcing
    // EnumResource::collection() wrap vs a scalar direct read): both members stay visible in the
    // merged union, so only the array-shaped one substitutes — the scalar arm is deliberately left bare.
    test('mixed ternary nested in an inline array: a collection-wrapped arm and a scalar direct arm substitute independently', function () {
        expect($this->props['wrapped_status_fallback']['type'])
            ->toBe('{ status: AsEnum<typeof Status>[] | StatusType }');
    });
});

describe('ResourceAstAnalyzer with ProductResource', function () {
    test('resolves multiple mergeWhen blocks', function () {
        $reflection = new ReflectionClass(ProductResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Product::class);
        $analysis = $analyzer->analyze();

        $weight = collect($analysis->properties)->firstWhere('name', 'weight');
        $metadata = collect($analysis->properties)->firstWhere('name', 'metadata');

        expect($weight['optional'])->toBeTrue()
            ->and($metadata['optional'])->toBeTrue();
    });

    test('resolves multiple whenAggregated calls', function () {
        $reflection = new ReflectionClass(ProductResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Product::class);
        $analysis = $analyzer->analyze();

        $totalSold = collect($analysis->properties)->firstWhere('name', 'total_sold');
        $minPrice = collect($analysis->properties)->firstWhere('name', 'min_unit_price');
        $maxPrice = collect($analysis->properties)->firstWhere('name', 'max_unit_price');

        expect($totalSold['type'])->toBe('number')
            ->and($minPrice['type'])->toBe('number')
            ->and($maxPrice['type'])->toBe('number');
    });
});

describe('ResourceAstAnalyzer with CategoryResource', function () {
    test('resolves self-referencing resource types', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $parent = collect($analysis->properties)->firstWhere('name', 'parent');
        $children = collect($analysis->properties)->firstWhere('name', 'children');

        expect($parent['type'])->toBe('CategoryResource')
            ->and($parent['optional'])->toBeTrue()
            ->and($children['type'])->toBe('CategoryResource[]')
            ->and($children['optional'])->toBeTrue();
    });

    // self:: and new self() expressions ———————————————————————————————————————

    test('self::collection() resolves to CategoryResource[]', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'children_self_collection');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource[]')
            ->and($prop['optional'])->toBeFalse();
    });

    test('self::collection() via $this->resource resolves to CategoryResource[]', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'children_self_resource_collection');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource[]')
            ->and($prop['optional'])->toBeFalse();
    });

    test('an empty-array default collapses into the array it defaults beside', function (string $name, string $expected) {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', $name);

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe($expected)
            ->and($prop['type'])->not->toContain('Record<')
            ->and($prop['type'])->not->toContain('never[]')
            ->and($prop['optional'])->toBeFalse();
    })->with([
        ['children_with_default', 'Category[]'],
        ['posts_with_default', 'PostResource[]'],
    ]);

    test('self::collection(...) first-class callable resolves to CategoryResource[]', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'children_self_collection_first_callable');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource[]')
            ->and($prop['optional'])->toBeFalse();
    });

    test('whenLoaded with self::collection() resolves to optional CategoryResource[]', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'children_when_self_collection');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource[]')
            ->and($prop['optional'])->toBeTrue();
    });

    test('whenLoaded with self::collection() via $this->resource resolves to optional CategoryResource[]', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'children_when_self_resource_collection');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource[]')
            ->and($prop['optional'])->toBeTrue();
    });

    test('whenLoaded with self::collection(...) FCC resolves to optional CategoryResource[]', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'children_when_self_collection_first_callable');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource[]')
            ->and($prop['optional'])->toBeTrue();
    });

    test('new self() resolves to CategoryResource', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_self');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource')
            ->and($prop['optional'])->toBeFalse();
    });

    test('self::make() resolves to CategoryResource', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_make_self');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource')
            ->and($prop['optional'])->toBeFalse();
    });

    test('new self() via $this->resource resolves to CategoryResource', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_resource_self');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource')
            ->and($prop['optional'])->toBeFalse();
    });

    test('whenLoaded with new self() in closure resolves to optional CategoryResource', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_when_self');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource')
            ->and($prop['optional'])->toBeTrue();
    });

    test('whenLoaded with new self() via $this->resource in closure resolves to optional CategoryResource', function () {
        $reflection = new ReflectionClass(CategoryResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_when_resource_self');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with FluentSelfResource', function () {
    test('new self($x)->fluentMethod() with a native : static return type preserves the resource type', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_fluent');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('FluentSelfResource')
            ->and($prop['optional'])->toBeTrue();
    });

    test('self::make($x)->fluentMethod() preserves the resource type', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_fluent_make');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('FluentSelfResource')
            ->and($prop['optional'])->toBeTrue();
    });

    test('a two-call fluent chain composes and still preserves the resource type', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_fluent_chain');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('FluentSelfResource')
            ->and($prop['optional'])->toBeTrue();
    });

    test('a fluent method with no native return type falls back to the @return $this docblock', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_fluent_docblock');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('FluentSelfResource')
            ->and($prop['optional'])->toBeTrue();
    });

    test('a chained method with a non-self return type resolves its body instead of degrading to unknown', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_summary');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ id: number }')
            ->and($prop['optional'])->toBeTrue();
    });

    test('a non-self-returning method on a foreign resource class stays at the unknown floor', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'foreign_summary');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('unknown')
            ->and($prop['optional'])->toBeTrue();
    });

    test('a chained method declaring ?static appends | null to the preserved resource type', function () {
        $reflection = new ReflectionClass(FluentSelfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Category::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'parent_fluent_nullable');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('FluentSelfResource | null')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with InvoiceResource', function () {
    test('resolves when wrapping EnumResource::make as optional', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $status = collect($analysis->properties)->firstWhere('name', 'status');

        expect($status['optional'])->toBeTrue();
    });

    test('latest_payment_only references the Payment model via Pick', function () {
        // latest_payment is an accessor returning Payment; every only() key is a plain Payment column, so
        // the analyzer references the emitted Payment model interface instead of an inline shape.
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'latest_payment_only');

        expect($prop['type'])->toBe(
            "Pick<Payment, 'invoice_id' | 'status' | 'method' | 'currency' | 'amount' | 'reference' | 'paid_at'> | null",
        );
    });

    test('latest_payment_excluded references the Payment model via Pick of the complement', function () {
        // Every except() key is also a plain Payment column, so this references the model interface too.
        // Pick<> of the surviving columns matches Model::except()'s real runtime shape — it never
        // returns dueNotice (a mutator) or invoice, see tests/Feature/ModelOnlyExceptSemanticsTest.php.
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'latest_payment_excluded');
        $type = (string) $prop['type'];

        preg_match_all("/'([a-zA-Z0-9_]+)'/", $type, $matches);
        $keys = $matches[1];
        sort($keys);

        expect($type)->toStartWith('Pick<Payment, ')
            ->and($type)->toEndWith('> | null')
            ->and($keys)->toBe(['created_at', 'id', 'updated_at']);
    });

    test('Pick/Omit accessor model filters register the Payment modelFqcn for import', function () {
        // Superseded the old embedded enum/model FQCN assertions (PaymentStatus/PaymentMethod/Currency and
        // a self-keyed Invoice FQCN embedded within the old inline shape): the resource now only needs to
        // import Payment itself — its own generated file already carries those enum and model imports.
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)
            ->toHaveKey('latest_payment_only', Payment::class)
            ->toHaveKey('latest_payment_excluded', Payment::class);
    });
});

describe('ResourceAstAnalyzer with DealResource', function () {
    test('resolves $this->property as direct property access', function () {
        $reflection = new ReflectionClass(DealResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Deal::class);
        $analysis = $analyzer->analyze();

        $status = collect($analysis->properties)->firstWhere('name', 'status');

        expect($status)->not->toBeNull()
            ->and($status['optional'])->toBeFalse();
    });

    test('resolves when with direct property value as optional', function () {
        $reflection = new ReflectionClass(DealResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Deal::class);
        $analysis = $analyzer->analyze();

        $closedAt = collect($analysis->properties)->firstWhere('name', 'closed_at');

        expect($closedAt['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with TrackingEventResource', function () {
    test('resolves direct properties when model attributes are available', function () {
        $reflection = new ReflectionClass(TrackingEventResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, TrackingEvent::class);
        $analysis = $analyzer->analyze();

        $status = collect($analysis->properties)->firstWhere('name', 'status');

        expect($status)->not->toBeNull()
            ->and($status['optional'])->toBeFalse();
    });

    test('resolves whenLoaded bare as optional', function () {
        $reflection = new ReflectionClass(TrackingEventResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, TrackingEvent::class);
        $analysis = $analyzer->analyze();

        $shipment = collect($analysis->properties)->firstWhere('name', 'shipment');

        expect($shipment['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with no model class', function () {
    test('returns unknown types when no model class provided', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, null);
        $analysis = $analyzer->analyze();

        $id = collect($analysis->properties)->firstWhere('name', 'id');

        expect($id['type'])->toBe('unknown');
    });
});

describe('ResourceAstAnalyzer with ReactionResource', function () {
    test('extracts whenLoaded properties as optional', function () {
        $reflection = new ReflectionClass(ReactionResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Reaction::class);
        $analysis = $analyzer->analyze();

        $article = collect($analysis->properties)->firstWhere('name', 'article');
        $user = collect($analysis->properties)->firstWhere('name', 'user');

        expect($article['optional'])->toBeTrue()
            ->and($user['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with OrderItemResource', function () {
    test('resolves Resource::make with whenLoaded', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        $product = collect($analysis->properties)->firstWhere('name', 'product');

        expect($product['type'])->toBe('ProductResource')
            ->and($product['optional'])->toBeTrue()
            ->and($analysis->nestedResources)->toHaveKey('product');
    });

    test('resolves bare whenLoaded as model type', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)->toHaveKey('order');
    });

    test('resolves BelongsTo with non-nullable FK without null', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        $order = collect($analysis->properties)->firstWhere('name', 'order');

        expect($order['type'])->toBe('Order');
    });

    test('order_limited only() with all-column keys references the Order model via Pick', function () {
        // order_limited = $this->order?->only('id', 'total') — both keys are plain Order columns, so the
        // analyzer now references the emitted Order model interface instead of re-deriving an inline shape.
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        $orderLimited = collect($analysis->properties)->firstWhere('name', 'order_limited');

        expect($orderLimited['type'])->toBe("Pick<Order, 'id' | 'total'> | null");
    });

    test('order_extended except() with all-column keys references the Order model via Pick of the complement', function () {
        // order_extended = $this->order->except('created_at', 'updated_at') — both excluded keys are plain
        // Order columns; Pick<> of the surviving columns matches ground truth exactly (Order also has
        // mutators and relations that except() never returns) — see tests/Feature/ModelOnlyExceptSemanticsTest.php.
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        $orderExtended = collect($analysis->properties)->firstWhere('name', 'order_extended');
        $type = (string) $orderExtended['type'];

        preg_match_all("/'([a-zA-Z0-9_]+)'/", $type, $matches);
        $keys = $matches[1];
        sort($keys);

        expect($type)->toStartWith('Pick<Order, ')
            ->and($type)->toEndWith('>')
            ->and($keys)->toBe([
                'billing_address', 'cancelled_at', 'currency', 'deleted_at', 'delivered_at', 'discount',
                'id', 'ip_address', 'notes', 'paid_at', 'payment_method', 'placed_at', 'shipped_at',
                'shipping_address', 'status', 'subtotal', 'tax', 'total', 'ulid', 'user_agent', 'user_id',
            ]);
    });

    test('Pick/Omit relation filters register the Order modelFqcn for import', function () {
        // Superseded the old embedded enum/model FQCN assertions: the inline expansion used to pull in
        // OrderStatus/PaymentMethod/Currency and the User/OrderItem FQCNs embedded within it directly.
        // Now the resource only needs to import Order itself.
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)
            ->toHaveKey('order_limited', Order::class)
            ->toHaveKey('order_extended', Order::class);
    });
});

describe('relation filters reference the emitted model interface', function () {
    test('only() on a belongsTo with all-column keys emits Pick of the model', function () {
        // CommentResource: post_limited = $this->post->only(['id', 'title']) — both keys are plain
        // Post columns, so the analyzer references the Post model interface instead of an inline shape.
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['post_limited']['type'])->toBe("Pick<Post, 'id' | 'title'>");
    });

    test('except() on a nullsafe belongsTo with all-column keys emits Pick of the complement, nullable', function () {
        // CommentResource: post_extended = $this->post?->except(['created_at', 'updated_at']). Post has
        // mutators and relations beyond its columns; Pick<Post, ...> of the surviving columns matches
        // Model::except()'s real runtime shape. See tests/Feature/ModelOnlyExceptSemanticsTest.php.
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['post_extended']['type'])->toStartWith('Pick<Post, ')
            ->and($props['post_extended']['type'])->not->toContain("'created_at'")
            ->and($props['post_extended']['type'])->not->toContain("'updated_at'")
            ->and($props['post_extended']['type'])->toEndWith('| null');
    });

    test('post_limited and post_extended register Post as their modelFqcn for import', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)
            ->toHaveKey('post_limited', Post::class)
            ->toHaveKey('post_extended', Post::class);
    });

    test('only() keeps keys quoted and unions them', function () {
        // OrderItemResource: order_extended = $this->order->except('created_at', 'updated_at') — variadic
        // args, not nullsafe, so no ' | null' suffix on this one (order_limited covers the nullsafe case).
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(OrderItemResource::class), OrderItem::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['order_extended']['type'])->toMatch("/^Pick<Order, '[a-z_]+'( \| '[a-z_]+')*>$/");
    });

    test('hasMany relation with all-column only() keys emits Pick with the [] suffix', function () {
        // PostResource: comments_limited = $this->comments->only(['id', 'content']) — HasMany, so the
        // many-relation [] suffix is preserved on top of the Pick<> reference.
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['comments_limited']['type'])->toBe("Pick<Comment, 'id' | 'content'>[]");
    });

    test('a filter key that is an accessor still falls back to inline expansion', function () {
        // CommentResource: post_excerpt_only = $this->post->only(['id', 'excerpt']) — 'excerpt' is an
        // Attribute accessor on Post with no backing column, so the whole filter falls back to inline
        // (the result must NOT be a Pick/Omit reference).
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['post_excerpt_only']['type'])
            ->not->toContain('Pick<')
            ->not->toContain('Omit<')
            ->toBe('{ id: number; excerpt: string | null }');
    });
});

describe('ResourceAstAnalyzer with MapRelationFilterResource (relation-filter vs. ->map proxy guard order)', function () {
    test('$this->map->only([...]) resolves through the relation-filter guard, not the ->map proxy', function () {
        // Team::map() is a real BelongsTo named literally 'map'. Both guards in
        // analyzeValueExpression() structurally match $this->map->only([...]); only the
        // relation-filter guard running first keeps this a Pick<User, ...> instead of unknown.
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(MapRelationFilterResource::class), Team::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['map']['type'])->toBe("Pick<User, 'id' | 'name'>");
    });
});

describe('ResourceAstAnalyzer with ShipmentResource', function () {
    test('extracts all expected property names', function () {
        $reflection = new ReflectionClass(ShipmentResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Shipment::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('carrier', 'status', 'tracking_number');
    });

    test('resolves mergeWhen with complex expression', function () {
        $reflection = new ReflectionClass(ShipmentResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Shipment::class);
        $analysis = $analyzer->analyze();

        $transitTime = collect($analysis->properties)->firstWhere('name', 'transit_time');

        expect($transitTime)->not->toBeNull()
            ->and($transitTime['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer returns empty analysis', function () {
    test('returns default ResourceAnalysis for empty properties', function () {
        $analysis = new ResourceAnalysis;

        expect($analysis->properties)->toBe([])
            ->and($analysis->enumResources)->toBe([])
            ->and($analysis->nestedResources)->toBe([])
            ->and($analysis->customImports)->toBe([])
            ->and($analysis->directEnumFqcns)->toBe([])
            ->and($analysis->modelFqcns)->toBe([]);
    });
});

describe('ResourceAstAnalyzer edge cases', function () {
    test('returns empty analysis when resource has no toArray method', function () {
        $reflection = new ReflectionClass(EmptyResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        expect($analysis->properties)->toBe([])
            ->and($analysis->enumResources)->toBe([])
            ->and($analysis->nestedResources)->toBe([]);
    });

    test('returns empty analysis when toArray does not return array literal', function () {
        $reflection = new ReflectionClass(DelegatingResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        expect($analysis->properties)->toBe([])
            ->and($analysis->enumResources)->toBe([])
            ->and($analysis->nestedResources)->toBe([]);
    });

    test('resolves bare whenLoaded relation as collection type', function () {
        $reflection = new ReflectionClass(OrderResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Order::class);
        $analysis = $analyzer->analyze();

        $items = collect($analysis->properties)->firstWhere('name', 'items');

        expect($items['type'])->toBe('OrderItem[]')
            ->and($items['optional'])->toBeTrue()
            ->and($analysis->modelFqcns)->toHaveKey('items');
    });

    test('resolves direct enum property types from model', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $id = collect($analysis->properties)->firstWhere('name', 'id');
        $title = collect($analysis->properties)->firstWhere('name', 'title');

        expect($id['type'])->toBe('number')
            ->and($title['type'])->toBe('string');
    });

    test('resolves nullable model attributes with null union', function () {
        $reflection = new ReflectionClass(OrderResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Order::class);
        $analysis = $analyzer->analyze();

        $total = collect($analysis->properties)->firstWhere('name', 'total');

        expect($total['type'])->toContain('number');
    });

    test('resolves enum FQCN from EnumResource::make for model property', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        expect($analysis->enumResources)->toHaveKey('status')
            ->and($analysis->enumResources['status'])->toBe(Status::class);
    });

    test('resolves enum FQCN from new EnumResource for model property', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        expect($analysis->enumResources)->toHaveKey('status_new')
            ->and($analysis->enumResources['status_new'])->toBe(Status::class)
            ->and($analysis->enumResources)->toHaveKey('visibility_new')
            ->and($analysis->enumResources['visibility_new'])->toBe(Visibility::class)
            ->and($analysis->enumResources)->toHaveKey('priority_new')
            ->and($analysis->enumResources['priority_new'])->toBe(Priority::class);
    });

    test('resolves direct enum property from whenHas', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $phone = collect($analysis->properties)->firstWhere('name', 'phone');

        expect($phone['type'])->not->toBe('unknown');
    });

    test('resolves singular relation from bare whenLoaded', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $profile = collect($analysis->properties)->firstWhere('name', 'profile');

        expect($profile['type'])->toBe('Profile | null')
            ->and($profile['optional'])->toBeTrue();
    });

    test('resolves singular relation without null when nullable_relations is false', function () {
        config()->set('ts-publish.models.nullable_relations', false);

        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $profile = collect($analysis->properties)->firstWhere('name', 'profile');

        expect($profile['type'])->toBe('Profile');
    });
});

describe('ResourceAstAnalyzer JsonResource base delegation', function () {
    test('returns model attributes when resource has no toArray method and model is known', function () {
        $reflection = new ReflectionClass(EmptyWithMixinResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($analysis->properties)->not->toBeEmpty()
            ->and($names)->toContain('id')
            ->and($names)->toContain('name')
            ->and($names)->toContain('email');
    });

    test('returns model attributes when resource delegates to parent::toArray with model', function () {
        $reflection = new ReflectionClass(DelegatingWithMixinResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($analysis->properties)->not->toBeEmpty()
            ->and($names)->toContain('id')
            ->and($names)->toContain('name')
            ->and($names)->toContain('email');
    });

    test('spreads model attributes from JsonResource base plus child keys', function () {
        $reflection = new ReflectionClass(SpreadJsonBaseResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('name')
            ->and($names)->toContain('full_name');
    });

    test('spread child key appears after model attributes', function () {
        $reflection = new ReflectionClass(SpreadJsonBaseResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');
        $idIndex = array_search('id', $names, true);
        $fullNameIndex = array_search('full_name', $names, true);

        expect($idIndex)->toBeLessThan($fullNameIndex);
    });

    test('model attributes are not optional', function () {
        $reflection = new ReflectionClass(EmptyWithMixinResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $id = collect($analysis->properties)->firstWhere('name', 'id');

        expect($id['optional'])->toBeFalse();
    });

    test('nullable model attributes include null union', function () {
        $reflection = new ReflectionClass(EmptyWithMixinResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $bio = collect($analysis->properties)->firstWhere('name', 'bio');

        expect($bio['type'])->toContain('| null');
    });

    test('enum cast attributes populate directEnumFqcns', function () {
        $reflection = new ReflectionClass(DelegatingWithMixinResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        expect($analysis->directEnumFqcns)->toHaveKey('role');
    });

    test('still returns empty when no toArray and no model is known', function () {
        $reflection = new ReflectionClass(EmptyResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        expect($analysis->properties)->toBe([]);
    });

    test('still returns empty when delegating and no model is known', function () {
        $reflection = new ReflectionClass(DelegatingResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        expect($analysis->properties)->toBe([]);
    });
});

describe('ResourceAstAnalyzer with OrderDetailResource', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderDetailResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resolves whenLoaded with value argument as optional', function () {
        $user = collect($this->analysis->properties)->firstWhere('name', 'user');

        expect($user['type'])->toBe('UserResource')
            ->and($user['optional'])->toBeTrue()
            ->and($this->analysis->nestedResources)->toHaveKey('user');
    });

    test('resolves EnumResource::make inside mergeWhen', function () {
        expect($this->analysis->enumResources)->toHaveKey('payment_status');
    });

    test('resolves direct enum property inside mergeWhen', function () {
        $paymentCurrency = collect($this->analysis->properties)->firstWhere('name', 'payment_currency');

        expect($paymentCurrency)->not->toBeNull()
            ->and($paymentCurrency['optional'])->toBeTrue();
    });

    test('resolves Resource::make inside mergeWhen', function () {
        $shippingUser = collect($this->analysis->properties)->firstWhere('name', 'shipping_user');

        expect($shippingUser['type'])->toBe('UserResource')
            ->and($shippingUser['optional'])->toBeTrue()
            ->and($this->analysis->nestedResources)->toHaveKey('shipping_user');
    });

    test('resolves bare whenLoaded inside mergeWhen', function () {
        $orderItems = collect($this->analysis->properties)->firstWhere('name', 'order_items');

        expect($orderItems)->not->toBeNull()
            ->and($orderItems['optional'])->toBeTrue()
            ->and($this->analysis->modelFqcns)->toHaveKey('order_items');
    });
});

describe('ResourceAstAnalyzer with QuirkyResource', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(QuirkyResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('skips bare string values with null key', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('bare_value');
    });

    test('skips integer keyed items', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('42');
    });

    test('resolves when with single arg as unknown optional', function () {
        $flag = collect($this->analysis->properties)->firstWhere('name', 'flag');

        expect($flag['type'])->toBe('unknown')
            ->and($flag['optional'])->toBeTrue();
    });

    test('extracts properties from $this->merge([...]) as non-optional', function () {
        $extra = collect($this->analysis->properties)->firstWhere('name', 'extra');

        expect($extra)->not->toBeNull()
            ->and($extra['type'])->toBe('string')
            ->and($extra['optional'])->toBeFalse();
    });

    test('handles mergeWhen with single arg gracefully', function () {
        // $this->mergeWhen(true) with 1 arg — mergeWhen requires 2 args
        expect($this->analysis->properties)->toBeArray();
    });

    test('resolves mergeWhen with closure returning array as optional properties', function () {
        $dynamic = collect($this->analysis->properties)->firstWhere('name', 'dynamic');

        expect($dynamic)->not->toBeNull()
            ->and($dynamic['type'])->toBe('string')
            ->and($dynamic['optional'])->toBeTrue();
    });

    test('resolves non-resource static call as unknown', function () {
        $formatted = collect($this->analysis->properties)->firstWhere('name', 'formatted');

        expect($formatted['type'])->toBe('unknown');
    });

    test('resolves Resource::make with non-conditional arg as non-optional', function () {
        $plainUser = collect($this->analysis->properties)->firstWhere('name', 'plain_user');

        expect($plainUser['type'])->toBe('UserResource')
            ->and($plainUser['optional'])->toBeFalse();
    });

    test('resolves Resource::make with no args as non-optional', function () {
        $emptyUser = collect($this->analysis->properties)->firstWhere('name', 'empty_user');

        expect($emptyUser['type'])->toBe('UserResource')
            ->and($emptyUser['optional'])->toBeFalse();
    });

    test('resolves EnumResource::make with no args as unknown', function () {
        $emptyEnum = collect($this->analysis->properties)->firstWhere('name', 'empty_enum');

        expect($emptyEnum['type'])->toBe('unknown');
    });

    test('resolves EnumResource::make first-class callable as unknown', function () {
        $fccEnum = collect($this->analysis->properties)->firstWhere('name', 'fcc_enum');

        expect($fccEnum['type'])->toBe('unknown')
            ->and($fccEnum['optional'])->toBeFalse();
    });

    test('resolves EnumResource::collection first-class callable as unknown', function () {
        $fccEnumCollection = collect($this->analysis->properties)->firstWhere('name', 'fcc_enum_collection');

        expect($fccEnumCollection['type'])->toBe('unknown')
            ->and($fccEnumCollection['optional'])->toBeFalse();
    });

    test('resolves nonexistent model attribute as unknown', function () {
        $fakeField = collect($this->analysis->properties)->firstWhere('name', 'fake_field');

        expect($fakeField['type'])->toBe('unknown');
    });

    test('resolves nonexistent relation from bare whenLoaded as unknown', function () {
        $fakeRelation = collect($this->analysis->properties)->firstWhere('name', 'fake_relation');

        expect($fakeRelation['type'])->toBe('unknown')
            ->and($fakeRelation['optional'])->toBeTrue();
    });

    test('handles mergeWhen with unusual array items', function () {
        $normalKey = collect($this->analysis->properties)->firstWhere('name', 'normal_merge_key');

        expect($normalKey)->not->toBeNull()
            ->and($normalKey['optional'])->toBeTrue();
    });

    test('resolves EnumResource::make with non-enum property as unknown', function () {
        $notEnum = collect($this->analysis->properties)->firstWhere('name', 'not_enum');

        expect($notEnum['type'])->toBe('unknown');
    });

    test('resolves new EnumResource with no args as unknown', function () {
        $emptyNewEnum = collect($this->analysis->properties)->firstWhere('name', 'empty_new_enum');

        expect($emptyNewEnum['type'])->toBe('unknown');
    });

    test('resolves new EnumResource with non-property arg as unknown', function () {
        $varNewEnum = collect($this->analysis->properties)->firstWhere('name', 'var_new_enum');

        expect($varNewEnum['type'])->toBe('unknown');
    });
});

describe('ResourceAstAnalyzer with non-existent model', function () {
    test('handles non-existent model class gracefully', function () {
        $reflection = new ReflectionClass(UserResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, 'App\Models\NonExistentModel');
        $analysis = $analyzer->analyze();

        $role = collect($analysis->properties)->firstWhere('name', 'role');
        $profile = collect($analysis->properties)->firstWhere('name', 'profile');
        $phone = collect($analysis->properties)->firstWhere('name', 'phone');

        expect($role['type'])->toBe('unknown')
            ->and($profile['type'])->toBe('unknown')
            ->and($profile['optional'])->toBeTrue()
            ->and($phone['type'])->toBe('unknown')
            ->and($phone['optional'])->toBeTrue()
            ->and($analysis->enumResources)->toBeEmpty()
            ->and($analysis->modelFqcns)->toBeEmpty();
    });
});

describe('ResourceAstAnalyzer with parent::toArray spread', function () {
    test('resolves properties from ...parent::toArray()', function () {
        $reflection = new ReflectionClass(ApiPostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        // Parent PostResource supplies id/title/content; ApiPostResource re-declares the three enum keys.
        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('title')
            ->and($names)->toContain('content');
    });

    test('parent properties appear before child properties', function () {
        $reflection = new ReflectionClass(ApiPostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');
        $idIndex = array_search('id', $names, true);
        $statusIndex = array_search('status', $names, true);

        expect($idIndex)->toBeLessThan($statusIndex);
    });

    test('child overrides clear parent enum resource tracking for overridden keys', function () {
        $reflection = new ReflectionClass(ApiPostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        // The child overrides status/visibility/priority with plain props; the _new variants stay untouched.
        expect($analysis->enumResources)
            ->not->toHaveKey('status')
            ->not->toHaveKey('visibility')
            ->not->toHaveKey('priority')
            ->toHaveKey('status_new')
            ->toHaveKey('visibility_new')
            ->toHaveKey('priority_new');
    });

    test('inherits customImports from parent trait TsCasts', function () {
        $reflection = new ReflectionClass(ExtendedAddressResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        expect($analysis->customImports)
            ->toHaveKey('@/types/geo')
            ->and($analysis->customImports['@/types/geo'])->toContain('GeoPoint');
    });
});

describe('ResourceAstAnalyzer with trait method spread', function () {
    test('resolves properties from ...$this->traitMethod() spread', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('morphValue');
    });

    test('trait spread properties appear before inline properties', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');
        $morphIndex = array_search('morphValue', $names, true);
        $idIndex = array_search('id', $names, true);

        expect($morphIndex)->toBeLessThan($idIndex);
    });

    test('resolves PHPDoc @return array shape types for trait method spread', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $morphValue = collect($analysis->properties)->firstWhere('name', 'morphValue');

        expect($morphValue['type'])->toBe('string');
    });

    test('trait spread properties are resolved for AddressResource', function () {
        $reflection = new ReflectionClass(AddressResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Address::class);
        $analysis = $analyzer->analyze();

        $morphValue = collect($analysis->properties)->firstWhere('name', 'morphValue');

        expect($morphValue['type'])->toBe('string');
    });

    test('trait spread flows through parent::toArray to child', function () {
        $reflection = new ReflectionClass(ApiPostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        // morphValue comes from PostResource's trait spread, inherited via parent::toArray
        expect($names)->toContain('morphValue');
    });
});

describe('ResourceAstAnalyzer with mutually recursive spread methods', function () {
    it('does not recurse forever when two spread methods reference each other', function () {
        $reflection = new ReflectionClass(MutuallyRecursiveSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Team::class);
        $analysis = $analyzer->analyze();

        $props = collect($analysis->properties)->keyBy('name');

        expect($props)->toHaveKey('name')
            ->and($props['name']['type'])->toBe('string');
    });
});

describe('ResourceAstAnalyzer with a case-mismatched spread call site', function () {
    it('resolves a spread method whose call-site casing differs from its declaration', function () {
        $reflection = new ReflectionClass(CaseSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $props = collect($analysis->properties)->keyBy('name');

        expect($props['id']['type'])->toBe('number')
            ->and($props['case_title']['type'])->toBe('string');
    });
});

describe('ResourceAstAnalyzer with a bare method-call return', function () {
    it('resolves a toArray that returns a method call, transitively', function () {
        $reflection = new ReflectionClass(BareMethodReturnResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Team::class);
        $analysis = $analyzer->analyze();

        $props = collect($analysis->properties)->keyBy('name');

        expect($props)->toHaveKey('id')
            ->and($props['id']['type'])->toBe('number')
            ->and($props)->toHaveKey('slug')
            ->and($props['slug']['type'])->toBe('string');
    });
});

describe('ResourceAstAnalyzer with a non-$this receiver in a spread method chain', function () {
    it('does not resolve a method call whose receiver is not $this, even when its name collides with a real method', function () {
        $reflection = new ReflectionClass(NonThisReceiverSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Team::class);
        $analysis = $analyzer->analyze();

        $props = collect($analysis->properties)->keyBy('name');

        // outer() returns $this->helper()->wrongCall() — helper() is not $this, so the resource's
        // own wrongCall() (which returns 'leaked') must not be resolved just because the name matches.
        expect($props)->toHaveKey('id')
            ->and($props)->not->toHaveKey('leaked')
            ->and($props)->not->toHaveKey('unrelated');
    });
});

describe('ResourceAstAnalyzer non-array return', function () {
    test('merges a returned array_merge() of array literals', function () {
        $reflection = new ReflectionClass(NonArrayReturnResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        expect($analysis)->toBeInstanceOf(ResourceAnalysis::class)
            ->and(array_column($analysis->properties, 'type', 'name'))->toBe(['id' => 'number', 'name' => 'string']);
    });

    test('array_merge and the spread form agree on a key redeclared over a parent', function () {
        // inlineModelFqcns is a positional queue aliasPropertyType() walks against the rendered type
        // string, so a merge strategy that concatenates per-argument analyses doubles it while the
        // string keeps two tokens — the child's arms then alias to the parent's models.
        $spread = new ResourceAstAnalyzer(new ReflectionClass(MergeSpreadChildResource::class), Warehouse::class)->analyze();
        $merge = new ResourceAstAnalyzer(new ReflectionClass(MergeArrayMergeChildResource::class), Warehouse::class)->analyze();

        expect($merge->inlineModelFqcns)->toBe(['who' => [CrmUser::class, User::class]])
            ->and($merge->inlineModelFqcns)->toBe($spread->inlineModelFqcns)
            ->and(array_column($merge->properties, 'type', 'name'))->toBe(['who' => 'User | User | null'])
            ->and(array_column($merge->properties, 'type', 'name'))->toBe(array_column($spread->properties, 'type', 'name'));
    });

    test('returns empty analysis for a returned call it cannot read statically', function () {
        $reflection = new ReflectionClass(UnreadableReturnResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        expect($analysis)->toBeInstanceOf(ResourceAnalysis::class)
            ->and($analysis->properties)->toBe([]);
    });
});

describe('ResourceAstAnalyzer trait spread doc type branches', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(TraitSpreadCoverageResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $this->analysis = $analyzer->analyze();
    });

    test('skips doc type resolution for already-known property types', function () {
        $id = collect($this->analysis->properties)->firstWhere('name', 'id');

        // id resolves to number from model, not overridden by docType 'string'
        expect($id['type'])->toBe('number');
    });

    test('resolves callable PHPDoc types via tsMap', function () {
        $dateVal = collect($this->analysis->properties)->firstWhere('name', 'date_val');

        // datetime maps to a callable in tsMap
        expect($dateVal['type'])->toBe('string');
    });

    test('passes through unmapped PHPDoc types as-is', function () {
        $customVal = collect($this->analysis->properties)->firstWhere('name', 'custom_val');

        // CustomObject is not in tsMap, passed through directly
        expect($customVal['type'])->toBe('CustomObject');
    });

    test('includes properties from trait methods without docblocks — type inferred from body', function () {
        $plain = collect($this->analysis->properties)->firstWhere('name', 'plain');

        expect($plain)->not->toBeNull()
            ->and($plain['type'])->toBe('string'); // strtoupper() resolves to string
    });

    test('includes properties from trait methods without array shape annotation — type inferred from body', function () {
        $basic = collect($this->analysis->properties)->firstWhere('name', 'basic');

        expect($basic)->not->toBeNull()
            ->and($basic['type'])->toBe('string'); // strtolower() resolves to string
    });

    test('resolves multiline @return array shape types', function () {
        $firstName = collect($this->analysis->properties)->firstWhere('name', 'firstName');
        $lastName = collect($this->analysis->properties)->firstWhere('name', 'lastName');
        $isActive = collect($this->analysis->properties)->firstWhere('name', 'isActive');

        expect($firstName['type'])->toBe('string')
            ->and($lastName['type'])->toBe('string')
            ->and($isActive['type'])->toBe('boolean');
    });

    test('applies TsCasts type overrides on trait methods', function () {
        $location = collect($this->analysis->properties)->firstWhere('name', 'location');

        expect($location['type'])->toBe('GeoPoint');
    });

    test('applies TsCasts optional flag on trait methods', function () {
        $flag = collect($this->analysis->properties)->firstWhere('name', 'flag');

        expect($flag['type'])->toBe('string | null')
            ->and($flag['optional'])->toBeTrue();
    });

    test('adds new properties from TsCasts on trait methods', function () {
        $extra = collect($this->analysis->properties)->firstWhere('name', 'extra');

        expect($extra)->not->toBeNull()
            ->and($extra['type'])->toBe('Record<string, unknown>')
            ->and($extra['optional'])->toBeFalse();
    });

    test('populates customImports from TsCasts import paths', function () {
        expect($this->analysis->customImports)->toBe(['@/types/geo' => ['GeoPoint']]);
    });
});

describe('ResourceAstAnalyzer with OrderSummaryResource', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderSummaryResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resolves accessor column (is_paid) via reflection', function () {
        $isPaid = collect($this->analysis->properties)->firstWhere('name', 'is_paid');

        expect($isPaid)->not->toBeNull()
            ->and($isPaid['type'])->toBe('boolean')
            ->and($isPaid['optional'])->toBeFalse();
    });

    test('resolves pure mutator (item_count) via reflection', function () {
        $itemCount = collect($this->analysis->properties)->firstWhere('name', 'item_count');

        expect($itemCount)->not->toBeNull()
            ->and($itemCount['type'])->toBe('number')
            ->and($itemCount['optional'])->toBeFalse();
    });

    test('resolves pure mutator (formatted_total) via reflection', function () {
        $formattedTotal = collect($this->analysis->properties)->firstWhere('name', 'formatted_total');

        expect($formattedTotal)->not->toBeNull()
            ->and($formattedTotal['type'])->toBe('string')
            ->and($formattedTotal['optional'])->toBeFalse();
    });

    test('resolves direct relation access (user) to model type', function () {
        $user = collect($this->analysis->properties)->firstWhere('name', 'user');

        expect($user)->not->toBeNull()
            ->and($user['type'])->toBe('User')
            ->and($user['optional'])->toBeFalse();
    });

    test('tracks direct relation model FQCN', function () {
        expect($this->analysis->modelFqcns)->toHaveKey('user');
    });

    test('resolves enum cast column (status) correctly', function () {
        $status = collect($this->analysis->properties)->firstWhere('name', 'status');

        expect($status)->not->toBeNull()
            ->and($status['type'])->toBe('OrderStatusType')
            ->and($status['optional'])->toBeFalse();
    });

    test('resolves regular DB column (total) correctly', function () {
        $total = collect($this->analysis->properties)->firstWhere('name', 'total');

        expect($total)->not->toBeNull()
            ->and($total['type'])->toBe('number')
            ->and($total['optional'])->toBeFalse();
    });

    test('tracks direct enum FQCN for enum cast column', function () {
        expect($this->analysis->directEnumFqcns)->toHaveKey('status');
    });

    test('resolves nullable accessor column (notes) with null union', function () {
        $notes = collect($this->analysis->properties)->firstWhere('name', 'notes');

        expect($notes)->not->toBeNull()
            ->and($notes['type'])->toBe('string | null')
            ->and($notes['optional'])->toBeFalse();
    });

    test('resolves write-only mutator (search_index) to unknown', function () {
        $searchIndex = collect($this->analysis->properties)->firstWhere('name', 'search_index');

        expect($searchIndex)->not->toBeNull()
            ->and($searchIndex['type'])->toBe('unknown')
            ->and($searchIndex['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer with OrderCountsResource', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderCountsResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resolves items_count virtual attribute (withCount) to number', function () {
        $itemsCount = collect($this->analysis->properties)->firstWhere('name', 'items_count');

        expect($itemsCount)->not->toBeNull()
            ->and($itemsCount['type'])->toBe('number')
            ->and($itemsCount['optional'])->toBeFalse();
    });

    test('resolves items_exists virtual attribute (withExists) to boolean', function () {
        $itemsExists = collect($this->analysis->properties)->firstWhere('name', 'items_exists');

        expect($itemsExists)->not->toBeNull()
            ->and($itemsExists['type'])->toBe('boolean')
            ->and($itemsExists['optional'])->toBeFalse();
    });

    test('resolves camelCase access to the formatted_total accessor', function () {
        $formattedTotalCamel = collect($this->analysis->properties)->firstWhere('name', 'formatted_total_camel');

        expect($formattedTotalCamel)->not->toBeNull()
            ->and($formattedTotalCamel['type'])->toBe('string')
            ->and($formattedTotalCamel['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer with OrderOnlyResource (spread only)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderOnlyResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('only spread includes exactly the listed properties', function () {
        $names = array_column($this->analysis->properties, 'name');

        // One needle per `not->toContain()`: Pest's opposite expectation passes on the first absent needle,
        // so a multi-needle call only asserts "at least one is missing" — which is how 'notes', named in
        // only() and correctly emitted, sat unchecked in the excluded list behind 'ulid'.
        expect($names)->toContain('id', 'total', 'status')
            ->and($names)->toContain('user')
            ->and($names)->toContain('notes')
            ->and($names)->not->toContain('ulid')
            ->and($names)->not->toContain('subtotal')
            ->and($names)->not->toContain('tax');
    });

    test('resolves types for only-listed properties', function () {
        $id = collect($this->analysis->properties)->firstWhere('name', 'id');
        $total = collect($this->analysis->properties)->firstWhere('name', 'total');
        $status = collect($this->analysis->properties)->firstWhere('name', 'status');

        expect($id['type'])->toBe('number')
            ->and($total['type'])->toBe('number')
            ->and($status['type'])->toBe('OrderStatusType');
    });

    test('preserves enum FQCN through only filter', function () {
        expect($this->analysis->directEnumFqcns)->toHaveKey('status')
            ->and($this->analysis->directEnumFqcns)->not->toHaveKey('total');
    });

    test('manual keys alongside only spread are preserved', function () {
        $user = collect($this->analysis->properties)->firstWhere('name', 'user');

        expect($user)->not->toBeNull()
            ->and($user['type'])->toBe('UserResource')
            ->and($user['optional'])->toBeTrue();
    });

    test('only() keeps an explicitly-named write-only mutator that except() would omit', function () {
        // Model::only() resolves through getAttribute(), which does return search_index (as null)
        // — unlike Model::except()/toArray(), which never returns it at all. Naming it in only()
        // is the caller's own explicit choice, so buildModelDelegatedAnalysis() must not drop it.
        $searchIndex = collect($this->analysis->properties)->firstWhere('name', 'search_index');

        expect($searchIndex)->not->toBeNull()
            ->and($searchIndex['type'])->toBe('unknown');
    });
});

describe('ResourceAstAnalyzer with OrderExceptResource (direct return)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderExceptResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('except excludes the listed properties', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('ip_address')
            ->and($names)->not->toContain('user_agent');
    });

    test('except includes non-excluded properties with correct types', function () {
        $id = collect($this->analysis->properties)->firstWhere('name', 'id');
        $total = collect($this->analysis->properties)->firstWhere('name', 'total');
        $status = collect($this->analysis->properties)->firstWhere('name', 'status');

        expect($id)->not->toBeNull()
            ->and($id['type'])->toBe('number')
            ->and($total)->not->toBeNull()
            ->and($total['type'])->toBe('number')
            ->and($status)->not->toBeNull()
            ->and($status['type'])->toBe('OrderStatusType');
    });

    test('except preserves enum FQCNs for non-excluded columns', function () {
        expect($this->analysis->directEnumFqcns)->toHaveKey('status');
    });

    test('nullable non-enum cast column includes null in type', function () {
        // paid_at is a nullable datetime cast — validates the regular cast branch adds | null
        $paidAt = collect($this->analysis->properties)->firstWhere('name', 'paid_at');

        expect($paidAt)->not->toBeNull()
            ->and($paidAt['type'])->toBe('string | null');
    });

    test('except() omits a write-only mutator Model::except() could never produce', function () {
        // search_index has no getter and no docblock generic, so it is never a real member of
        // the attributes bag except() derives its property set from — unlike a named exclusion,
        // it must never surface as `search_index: unknown`.
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('search_index');
    });
});

describe('ResourceAstAnalyzer with OrderFilterEdgeResource (edge cases)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderFilterEdgeResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, null))->analyze();
    });

    test('variable arg in only() is gracefully skipped', function () {
        // ...$this->only($request->input(...)) has non-Array_ arg — returns null, skipped
        expect($this->analysis->properties)->toBeArray();
    });

    test('empty array in except() is gracefully skipped', function () {
        // ...$this->except([]) has empty keys — returns null, skipped
        expect($this->analysis->properties)->toBeArray();
    });

    test('valid keys with no model returns empty analysis', function () {
        // ...$this->only(['id', 'name']) has valid keys but no model — buildModelDelegatedAnalysis returns null
        expect($this->analysis->properties)->toBeEmpty()
            ->and($this->analysis->directEnumFqcns)->toBeEmpty();
    });
});

describe('ResourceAstAnalyzer with TagResource (first-class callable collection)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(TagResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Tag::class))->analyze();
    });

    test('does not crash on Resource::collection(...) first-class callable syntax', function () {
        expect($this->analysis)->toBeInstanceOf(ResourceAnalysis::class);
    });

    test('resolves first-class callable collection to resource array type', function () {
        $posts = collect($this->analysis->properties)->firstWhere('name', 'posts');
        $products = collect($this->analysis->properties)->firstWhere('name', 'products');

        expect($posts)->not->toBeNull()
            ->and($posts['type'])->toBe('PostResource[]')
            ->and($posts['optional'])->toBeTrue()
            ->and($products)->not->toBeNull()
            ->and($products['type'])->toBe('ProductResource[]')
            ->and($products['optional'])->toBeTrue();
    });

    test('tracks nested resource FQCNs for first-class callable collections', function () {
        expect($this->analysis->nestedResources)->toHaveKey('posts')
            ->and($this->analysis->nestedResources)->toHaveKey('products');
    });
});

describe('ResourceAstAnalyzer with OrderClosureResource', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resolves arrow function returning $this->property', function () {
        $statusArrow = collect($this->analysis->properties)->firstWhere('name', 'status_arrow');

        expect($statusArrow)->not->toBeNull()
            ->and($statusArrow['optional'])->toBeTrue();
    });

    test('resolves arrow function returning Resource::make()', function () {
        $userArrow = collect($this->analysis->properties)->firstWhere('name', 'user_arrow');

        expect($userArrow)->not->toBeNull()
            ->and($userArrow['type'])->toBe('UserResource')
            ->and($userArrow['optional'])->toBeTrue();
    });

    test('resolves arrow function returning Resource::collection()', function () {
        $itemsArrow = collect($this->analysis->properties)->firstWhere('name', 'items_arrow');

        expect($itemsArrow)->not->toBeNull()
            ->and($itemsArrow['type'])->toBe('OrderItemResource[]')
            ->and($itemsArrow['optional'])->toBeTrue();
    });

    test('resolves full closure with return statement', function () {
        $notesClosure = collect($this->analysis->properties)->firstWhere('name', 'notes_closure');

        expect($notesClosure)->not->toBeNull()
            ->and($notesClosure['type'])->toBe('string | null')
            ->and($notesClosure['optional'])->toBeTrue();
    });

    test('resolves mergeWhen with closure returning array as optional', function () {
        $shippedAt = collect($this->analysis->properties)->firstWhere('name', 'shipped_at');
        $tracking = collect($this->analysis->properties)->firstWhere('name', 'tracking');

        expect($shippedAt)->not->toBeNull()
            ->and($shippedAt['optional'])->toBeTrue()
            ->and($tracking)->not->toBeNull()
            ->and($tracking['optional'])->toBeTrue();
    });

    test('resolves merge with closure returning array as non-optional', function () {
        $currencyLabel = collect($this->analysis->properties)->firstWhere('name', 'currency_label');

        expect($currencyLabel)->not->toBeNull()
            ->and($currencyLabel['optional'])->toBeFalse();
    });

    test('resolves merge with array literal as non-optional', function () {
        $totalDisplay = collect($this->analysis->properties)->firstWhere('name', 'total_display');

        expect($totalDisplay)->not->toBeNull()
            ->and($totalDisplay['type'])->toBe('number')
            ->and($totalDisplay['optional'])->toBeFalse();
    });

    test('tracks nested resource FQCNs from closure expressions', function () {
        expect($this->analysis->nestedResources)->toHaveKey('user_arrow')
            ->and($this->analysis->nestedResources)->toHaveKey('items_arrow');
    });
});

describe('ResourceAstAnalyzer with UserCollection (explicit $collects)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(UserCollection::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection))->analyze();
    });

    test('resolves $this->collection to singular resource array type', function () {
        $data = collect($this->analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('UserResource[]')
            ->and($data['optional'])->toBeFalse();
    });

    test('tracks singular resource FQCN in nestedResources', function () {
        expect($this->analysis->nestedResources)
            ->toHaveKey('data')
            ->and($this->analysis->nestedResources['data'])->toBe(UserResource::class);
    });

    test('resolves non-collection properties normally', function () {
        $hasAdmin = collect($this->analysis->properties)->firstWhere('name', 'has_admin');

        expect($hasAdmin)->not->toBeNull()
            ->and($hasAdmin['type'])->toBe('boolean');
    });
});

describe('ResourceAstAnalyzer with OrderCollection (explicit $collects)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderCollection::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection))->analyze();
    });

    test('resolves $this->collection via explicit $collects property', function () {
        $data = collect($this->analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('OrderResource[]')
            ->and($data['optional'])->toBeFalse();
    });

    test('tracks OrderResource FQCN in nestedResources', function () {
        expect($this->analysis->nestedResources)
            ->toHaveKey('data')
            ->and($this->analysis->nestedResources['data'])->toBe(OrderResource::class);
    });

    test('resolves $this->collection->count() to number', function () {
        $totalCount = collect($this->analysis->properties)->firstWhere('name', 'total_count');

        expect($totalCount)->not->toBeNull()
            ->and($totalCount['type'])->toBe('number')
            ->and($totalCount['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer with MiscCollection (unresolvable singular)', function () {
    test('falls back to unknown when singular resource cannot be resolved', function () {
        $reflection = new ReflectionClass(MiscCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();

        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('unknown')
            ->and($analysis->nestedResources)->not->toHaveKey('data');
    });
});

describe('ResourceAstAnalyzer with bare function call spread', function () {
    test('resolves bare function call trait methods as spread properties', function () {
        $reflection = new ReflectionClass(BareFuncCallResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('morphValue')
            ->toContain('id')
            ->toContain('computed')
            ->toContain('date_val')
            ->toContain('custom_val')
            ->toContain('plain')
            ->toContain('basic')
            ->toContain('firstName')
            ->toContain('lastName')
            ->toContain('isActive')
            ->toContain('location')
            ->toContain('flag')
            ->toContain('extra');
    });

    test('resolves PHPDoc types from bare function call spreads', function () {
        $reflection = new ReflectionClass(BareFuncCallResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        $morphValue = collect($analysis->properties)->firstWhere('name', 'morphValue');
        $firstName = collect($analysis->properties)->firstWhere('name', 'firstName');
        $isActive = collect($analysis->properties)->firstWhere('name', 'isActive');

        expect($morphValue['type'])->toBe('string')
            ->and($firstName['type'])->toBe('string')
            ->and($isActive['type'])->toBe('boolean');
    });

    test('resolves TsCasts from bare function call spreads', function () {
        $reflection = new ReflectionClass(BareFuncCallResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Comment::class);
        $analysis = $analyzer->analyze();

        $location = collect($analysis->properties)->firstWhere('name', 'location');
        $flag = collect($analysis->properties)->firstWhere('name', 'flag');
        $extra = collect($analysis->properties)->firstWhere('name', 'extra');

        expect($location['type'])->toBe('GeoPoint')
            ->and($flag['type'])->toBe('string | null')
            ->and($flag['optional'])->toBeTrue()
            ->and($extra['type'])->toBe('Record<string, unknown>')
            ->and($analysis->customImports)->toHaveKey('@/types/geo');
    });
});

describe('ResourceAstAnalyzer with variable-return trait method spreads', function () {
    test('resolves base array properties from variable return', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('id')
            ->toContain('baseKey')
            ->toContain('always');
    });

    test('resolves PHPDoc types on variable-return method', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $baseKey = collect($analysis->properties)->firstWhere('name', 'baseKey');

        expect($baseKey['type'])->toBe('string');
    });

    test('marks unconditional dim assignments as not optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $always = collect($analysis->properties)->firstWhere('name', 'always');

        expect($always)->not->toBeNull()
            ->and($always['optional'])->toBeFalse()
            ->and($always['type'])->toBe('string'); // strtoupper() → string
    });

    test('marks conditional dim assignments inside if blocks as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $conditionalKey = collect($analysis->properties)->firstWhere('name', 'conditionalKey');
        $sometimes = collect($analysis->properties)->firstWhere('name', 'sometimes');

        expect($conditionalKey)->not->toBeNull()
            ->and($conditionalKey['optional'])->toBeTrue()
            ->and($conditionalKey['type'])->toBe('string') // strtolower() → string
            ->and($sometimes)->not->toBeNull()
            ->and($sometimes['optional'])->toBeTrue()
            ->and($sometimes['type'])->toBe('string'); // strtolower() → string
    });

    test('marks elseif and else branch assignments as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $ifBranch = collect($analysis->properties)->firstWhere('name', 'ifBranch');
        $elseifBranch = collect($analysis->properties)->firstWhere('name', 'elseifBranch');
        $elseBranch = collect($analysis->properties)->firstWhere('name', 'elseBranch');

        expect($ifBranch['optional'])->toBeTrue()
            ->and($elseifBranch['optional'])->toBeTrue()
            ->and($elseBranch['optional'])->toBeTrue();
    });

    test('resolves all properties from all variable-return methods', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('id')
            ->toContain('baseKey')
            ->toContain('conditionalKey')
            ->toContain('always')
            ->toContain('sometimes')
            ->toContain('ifBranch')
            ->toContain('elseifBranch')
            ->toContain('elseBranch');
    });

    test('resolves a spread method whose return is itself a method call, transitively', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        // includeFromMethodCall() returns $this->includeNonAnalyzable(), which now resolves.
        $dynamic = collect($analysis->properties)->firstWhere('name', 'dynamic');

        expect($dynamic)->not->toBeNull()
            ->and($dynamic['type'])->toBe('string');
    });

    test('marks conditional base array assignment properties as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $conditionalBaseKey = collect($analysis->properties)->firstWhere('name', 'conditionalBaseKey');

        expect($conditionalBaseKey)->not->toBeNull()
            ->and($conditionalBaseKey['optional'])->toBeTrue();
    });

    test('marks foreach loop dim assignments as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $foreachKey = collect($analysis->properties)->firstWhere('name', 'foreachKey');

        expect($foreachKey)->not->toBeNull()
            ->and($foreachKey['optional'])->toBeTrue();
    });

    test('marks for loop dim assignments as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $forKey = collect($analysis->properties)->firstWhere('name', 'forKey');

        expect($forKey)->not->toBeNull()
            ->and($forKey['optional'])->toBeTrue();
    });

    test('marks while loop dim assignments as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $whileKey = collect($analysis->properties)->firstWhere('name', 'whileKey');

        expect($whileKey)->not->toBeNull()
            ->and($whileKey['optional'])->toBeTrue();
    });

    test('marks do-while loop dim assignments as optional', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $doWhileKey = collect($analysis->properties)->firstWhere('name', 'doWhileKey');

        expect($doWhileKey)->not->toBeNull()
            ->and($doWhileKey['optional'])->toBeTrue();
    });

    test('resolves all loop properties in variable-return methods', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('foreachKey')
            ->toContain('forKey')
            ->toContain('whileKey')
            ->toContain('doWhileKey');
    });

    test('de-duplicates repeated key assignments keeping correct optionality', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $statusProps = collect($analysis->properties)->where('name', 'status');

        expect($statusProps)->toHaveCount(1);

        expect($statusProps->first()['optional'])->toBeFalse();
    });

    test('resolves strtolower/strtoupper function calls to string in if/elseif/else branches', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $ifBranch = collect($analysis->properties)->firstWhere('name', 'ifBranch');
        $elseifBranch = collect($analysis->properties)->firstWhere('name', 'elseifBranch');
        $elseBranch = collect($analysis->properties)->firstWhere('name', 'elseBranch');

        expect($ifBranch['type'])->toBe('string')
            ->and($elseifBranch['type'])->toBe('string')
            ->and($elseBranch['type'])->toBe('string');
    });

    test('resolves strtoupper() inside conditional base array assignment to string', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $conditionalBaseKey = collect($analysis->properties)->firstWhere('name', 'conditionalBaseKey');

        expect($conditionalBaseKey['type'])->toBe('string');
    });

    test('resolves strtolower/strtoupper inside loop bodies to string', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $foreachKey = collect($analysis->properties)->firstWhere('name', 'foreachKey');
        $forKey = collect($analysis->properties)->firstWhere('name', 'forKey');
        $whileKey = collect($analysis->properties)->firstWhere('name', 'whileKey');
        $doWhileKey = collect($analysis->properties)->firstWhere('name', 'doWhileKey');

        expect($foreachKey['type'])->toBe('string')
            ->and($forKey['type'])->toBe('string')
            ->and($whileKey['type'])->toBe('string')
            ->and($doWhileKey['type'])->toBe('string');
    });

    test('resolves strtolower() on unconditional + conditional duplicate key to string', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        $status = collect($analysis->properties)->firstWhere('name', 'status');

        expect($status['type'])->toBe('string')
            ->and($status['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer with ApiArticleResource (abstract parent + only + enum)', function () {
    test('resolves properties from parent CommonResource trait method spreads', function () {
        $reflection = new ReflectionClass(ApiArticleResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Article::class);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('morphValue')
            ->toContain('firstName')
            ->toContain('lastName')
            ->toContain('isActive')
            ->toContain('location')
            ->toContain('flag');
    });

    test('resolves $this->only() spread properties with Article model types', function () {
        $reflection = new ReflectionClass(ApiArticleResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Article::class);
        $analysis = $analyzer->analyze();

        $title = collect($analysis->properties)->firstWhere('name', 'title');
        $slug = collect($analysis->properties)->firstWhere('name', 'slug');
        $excerpt = collect($analysis->properties)->firstWhere('name', 'excerpt');
        $body = collect($analysis->properties)->firstWhere('name', 'body');

        expect($title['type'])->toBe('string')
            ->and($slug['type'])->toBe('string')
            ->and($excerpt['type'])->toBe('string | null')
            ->and($body['type'])->toBe('string');
    });

    test('resolves EnumResource::make to ArticleStatus enum', function () {
        $reflection = new ReflectionClass(ApiArticleResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Article::class);
        $analysis = $analyzer->analyze();

        expect($analysis->enumResources)
            ->toHaveKey('status')
            ->and($analysis->enumResources['status'])->toBe(ArticleStatus::class);
    });

    test('resolves new EnumResource to ContentType enum', function () {
        $reflection = new ReflectionClass(ApiArticleResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Article::class);
        $analysis = $analyzer->analyze();

        expect($analysis->enumResources)
            ->toHaveKey('content_type')
            ->and($analysis->enumResources['content_type'])->toBe(ContentType::class);
    });

    test('resolves whenLoaded author as optional with User model', function () {
        $reflection = new ReflectionClass(ApiArticleResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Article::class);
        $analysis = $analyzer->analyze();

        $author = collect($analysis->properties)->firstWhere('name', 'author');

        expect($author['type'])->toBe('User')
            ->and($author['optional'])->toBeTrue()
            ->and($analysis->modelFqcns)->toHaveKey('author');
    });

    test('child only id overrides parent trait id', function () {
        $reflection = new ReflectionClass(ApiArticleResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Article::class);
        $analysis = $analyzer->analyze();

        // CommonResource's trait 'id' is string via PHPDoc; the child's only(['id']) resolves it on Article.
        $id = collect($analysis->properties)->firstWhere('name', 'id');

        expect($id['type'])->toBe('number');
    });
});

describe('ResourceAstAnalyzer with MediaTypeResource (model-less enum resource)', function () {
    test('early null return guard does not prevent array analysis', function () {
        $reflection = new ReflectionClass(MediaTypeResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('name', 'value', 'meta');
    });

    test('wrapped resource name property resolves to string', function () {
        $reflection = new ReflectionClass(MediaTypeResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $name = collect($analysis->properties)->firstWhere('name', 'name');

        expect($name['type'])->toBe('string');
    });

    test('wrapped resource value property resolves to string for string-backed enum', function () {
        $reflection = new ReflectionClass(MediaTypeResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $value = collect($analysis->properties)->firstWhere('name', 'value');

        expect($value['type'])->toBe('string');
    });

    test('inline array value is analyzed as inline object type', function () {
        $reflection = new ReflectionClass(MediaTypeResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $meta = collect($analysis->properties)->firstWhere('name', 'meta');

        expect($meta['type'])->toStartWith('{ ')->toEndWith(' }')
            ->toContain('extensions: unknown[]')
            ->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });

    test('generic this method call infers type from return annotation', function () {
        $reflection = new ReflectionClass(MediaTypeResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $meta = collect($analysis->properties)->firstWhere('name', 'meta');

        // maxSizeMb(): int → number, icon(): string → string
        expect($meta['type'])->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });

    test('$this->resource->method() resolves return type from wrapped class', function () {
        $reflection = new ReflectionClass(MediaTypeResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $meta = collect($analysis->properties)->firstWhere('name', 'meta');

        // extensions() returns array on MediaType enum → unknown[]
        expect($meta['type'])->toContain('extensions: unknown[]');
    });
});

describe('ResourceAstAnalyzer @var union docblock edge cases', function () {
    test('null-first @var docblock resolves backing type correctly', function () {
        $reflection = new ReflectionClass(EnumNullFirstResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $value = collect($analysis->properties)->firstWhere('name', 'value');

        expect($value['type'])->toBe('string');
    });

    test('model-backed resource using $this->resource->prop resolves model attribute type', function () {
        $reflection = new ReflectionClass(ModelWrappedPropResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $title = collect($analysis->properties)->firstWhere('name', 'title');

        expect($title['type'])->toBe('string');
    });
});

describe('ResourceAstAnalyzer with MediaTypeInstanceOfResource (instanceof guard clause)', function () {
    test('instanceof guard clause does not prevent array analysis', function () {
        $reflection = new ReflectionClass(MediaTypeInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($names)->toContain('name', 'value', 'meta');
    });

    test('wrapped resource name property resolves to string via instanceof hint', function () {
        $reflection = new ReflectionClass(MediaTypeInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $name = collect($analysis->properties)->firstWhere('name', 'name');

        expect($name['type'])->toBe('string');
    });

    test('wrapped resource value property resolves to string for string-backed enum via instanceof hint', function () {
        $reflection = new ReflectionClass(MediaTypeInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $value = collect($analysis->properties)->firstWhere('name', 'value');

        expect($value['type'])->toBe('string');
    });

    test('inline array includes resolved method types via instanceof hint', function () {
        $reflection = new ReflectionClass(MediaTypeInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $meta = collect($analysis->properties)->firstWhere('name', 'meta');

        expect($meta['type'])->toStartWith('{ ')->toEndWith(' }')
            ->toContain('extensions: unknown[]')
            ->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });
});

describe('ResourceAstAnalyzer with MediaTypeUnknownResource (no type hints)', function () {
    test('produces unknown types when no @var or instanceof hints exist', function () {
        $reflection = new ReflectionClass(MediaTypeUnknownResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $name = collect($analysis->properties)->firstWhere('name', 'name');
        $value = collect($analysis->properties)->firstWhere('name', 'value');

        expect($name['type'])->toBe('unknown');
        expect($value['type'])->toBe('unknown');
    });
});

describe('ResourceAstAnalyzer with MediaTypePositiveInstanceOfResource (positive instanceof guard)', function () {
    test('positive instanceof guard resolves enum type for properties', function () {
        $reflection = new ReflectionClass(MediaTypePositiveInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $name = collect($analysis->properties)->firstWhere('name', 'name');
        $value = collect($analysis->properties)->firstWhere('name', 'value');

        expect($name['type'])->toBe('string');
        expect($value['type'])->toBe('string');
    });

    test('empty inline array resolves to never[], the shape json_encode actually emits', function () {
        $reflection = new ReflectionClass(MediaTypePositiveInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $empty = collect($analysis->properties)->firstWhere('name', 'empty');

        expect($empty['type'])->toBe('never[]');
    });

    test('inline array with optional key marks it as optional', function () {
        $reflection = new ReflectionClass(MediaTypePositiveInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $meta = collect($analysis->properties)->firstWhere('name', 'meta');

        expect($meta['type'])->toContain('label?:');
    });
});

describe('ResourceAstAnalyzer with UnitEnumResource (unit enum wrapping)', function () {
    test('unit enum name resolves to string', function () {
        $reflection = new ReflectionClass(UnitEnumResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $name = collect($analysis->properties)->firstWhere('name', 'name');

        expect($name['type'])->toBe('string');
    });

    test('unit enum value falls back to string | number', function () {
        $reflection = new ReflectionClass(UnitEnumResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $value = collect($analysis->properties)->firstWhere('name', 'value');

        expect($value['type'])->toBe('string | number');
    });

    test('unknown enum property resolves to unknown', function () {
        $reflection = new ReflectionClass(UnitEnumResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $custom = collect($analysis->properties)->firstWhere('name', 'custom');

        expect($custom['type'])->toBe('unknown');
    });
});

describe('ResourceAstAnalyzer with ModelWrappedPropResource (model $this->resource->prop)', function () {
    test('model property accessed through $this->resource-> resolves correctly', function () {
        $reflection = new ReflectionClass(ModelWrappedPropResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $title = collect($analysis->properties)->firstWhere('name', 'title');

        expect($title['type'])->toBe('string');
    });
});

describe('ResourceAstAnalyzer with SpreadWithClosureResource (Bug 1: findBestArrayReturn scope boundary)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(SpreadWithClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, User::class))->analyze();
    });

    test('selects the outer toArray return, not the nested closure return', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('profile_bio')
            ->and($names)->not->toContain('profile_avatar')
            ->and($names)->not->toContain('profile_theme')
            ->and($names)->not->toContain('profile_locale');
    });

    test('includes model attributes from ...parent::toArray() spread', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('name')
            ->and($names)->toContain('email');
    });

    test('includes the whenLoaded key as an optional property', function () {
        $metadata = collect($this->analysis->properties)->firstWhere('name', 'metadata');

        expect($metadata)->not->toBeNull()
            ->and($metadata['optional'])->toBeTrue();
    });

    test('whenLoaded closure resolves to an inline object type', function () {
        $metadata = collect($this->analysis->properties)->firstWhere('name', 'metadata');

        expect($metadata)->not->toBeNull()
            ->and($metadata['type'])->toContain('profile_bio')
            ->and($metadata['type'])->toContain('profile_avatar')
            ->and($metadata['type'])->toContain('profile_theme')
            ->and($metadata['type'])->toContain('profile_locale');
    });

    test('model attributes appear before the whenLoaded key', function () {
        $names = array_column($this->analysis->properties, 'name');
        $idIndex = array_search('id', $names, true);
        $metadataIndex = array_search('metadata', $names, true);

        expect($idIndex)->toBeLessThan($metadataIndex);
    });
});

describe('ResourceAstAnalyzer with GuardClauseClosureResource (Bug 2: resolveClosureReturnExpression guard clause)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(GuardClauseClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resolves closure past guard clause to the data array, not null', function () {
        $buyer = collect($this->analysis->properties)->firstWhere('name', 'buyer');

        expect($buyer)->not->toBeNull()
            ->and($buyer['type'])->not->toBe('null')
            ->and($buyer['type'])->toContain('name')
            ->and($buyer['type'])->toContain('email');
    });

    test('resolves chained $this->relation->property types against the related model', function () {
        $buyer = collect($this->analysis->properties)->firstWhere('name', 'buyer');

        // $this->user->name is an accessor on User with return type string
        expect($buyer['type'])->toContain('name: string')
            // $this->user->email is a column on User (string)
            ->and($buyer['type'])->toContain('email: string');
    });

    test('whenLoaded with guard-clause closure is optional', function () {
        $buyer = collect($this->analysis->properties)->firstWhere('name', 'buyer');

        expect($buyer)->not->toBeNull()
            ->and($buyer['optional'])->toBeTrue();
    });

    test('explicit properties are still extracted alongside the closure key', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('total')
            ->and($names)->toContain('buyer');
    });
});

describe('ResourceAstAnalyzer with SpreadWithGuardClauseClosureResource (Bug 1 + Bug 2 combined)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(SpreadWithGuardClauseClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('selects the outer toArray return, not the nested closure return', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('phone')
            ->and($names)->not->toContain('avatar')
            ->and($names)->not->toContain('role');
    });

    test('includes model attributes from ...parent::toArray() spread', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('total');
    });

    test('includes the customer whenLoaded key as an optional property', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        expect($customer)->not->toBeNull()
            ->and($customer['optional'])->toBeTrue();
    });

    test('customer closure resolves past guard clause to inline object shape', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        expect($customer)->not->toBeNull()
            ->and($customer['type'])->not->toBe('null')
            ->and($customer['type'])->toContain('name')
            ->and($customer['type'])->toContain('email')
            ->and($customer['type'])->toContain('phone')
            ->and($customer['type'])->toContain('avatar')
            ->and($customer['type'])->toContain('role');
    });

    test('resolves $variable->property types against the related model', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // $user->name — accessor on User with string return type
        expect($customer['type'])->toContain('name: string')
            // $user->email — string column on User
            ->and($customer['type'])->toContain('email: string')
            // $user->phone — nullable string column on User
            ->and($customer['type'])->toContain('phone: string | null')
            // $user->avatar — nullable string column on User
            ->and($customer['type'])->toContain('avatar: string | null');
    });

    test('resolves $variable->accessor against the related model', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // $user->is_premium — accessor on User with bool return type
        expect($customer['type'])->toContain('is_premium: boolean');
    });

    test('resolves $variable enum-cast property against the related model', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // $user->role — cast to Role enum on User, nullable
        expect($customer['type'])->toContain('role: RoleType | null');
    });

    test('resolves $variable->method() return type against the related model', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // $user->nameTitled() — instance method on User with : string return type
        expect($customer['type'])->toContain('name_titled: string');
    });

    test('resolves $variable::staticMethod() return type against the related model', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // $user::morphValue() — static method on User with : string return type
        expect($customer['type'])->toContain('morph: string');
    });

    test('model attributes appear before the customer whenLoaded key', function () {
        $names = array_column($this->analysis->properties, 'name');
        $idIndex = array_search('id', $names, true);
        $customerIndex = array_search('customer', $names, true);

        expect($idIndex)->toBeLessThan($customerIndex);
    });

    test('enum FQCNs from inside inline object are propagated to directEnumFqcns', function () {
        // $user->role is cast to Role::class inside the nested inline object for customer.
        expect($this->analysis->directEnumFqcns)->toContain(Role::class);
    });
});

describe('ResourceAstAnalyzer with SpreadWithGuardDoubleClosureReturnResource (union return types)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(SpreadWithGuardDoubleClosureReturnResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('customer closure produces a union of two object shapes plus null', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        expect($customer)->not->toBeNull()
            ->and($customer['type'])->toContain('|')
            ->and($customer['type'])->toEndWith('| null');
    });

    test('first object shape contains the premium-path keys', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // The premium path has 'initials' but NOT 'name_titled' or 'morph'
        expect($customer['type'])->toContain('initials');
    });

    test('second object shape contains the default-path keys', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // The default path has 'name_titled' and 'morph' but NOT 'initials'
        expect($customer['type'])->toContain('name_titled')
            ->and($customer['type'])->toContain('morph');
    });

    test('both shapes share common keys with resolved types', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        // Both branches include name, email, phone, avatar, role, is_premium
        expect($customer['type'])->toContain('name: string')
            ->and($customer['type'])->toContain('email: string')
            ->and($customer['type'])->toContain('is_premium: boolean');
    });

    test('customer is marked as optional from whenLoaded', function () {
        $customer = collect($this->analysis->properties)->firstWhere('name', 'customer');

        expect($customer)->not->toBeNull()
            ->and($customer['optional'])->toBeTrue();
    });

    test('model attributes from parent::toArray spread are still present', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('total')
            ->and($names)->toContain('customer');
    });

    test('enum FQCNs from both union branches are propagated', function () {
        // role is cast to Role::class and appears in both branches
        expect($this->analysis->directEnumFqcns)->toContain(Role::class);
    });
});

describe('ResourceAstAnalyzer with ClosureControlFlowResource (collectReturnExpressions control flow)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ClosureControlFlowResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resolves closure with if/elseif/else branches into union type', function () {
        $buyerInfo = collect($this->analysis->properties)->firstWhere('name', 'buyer_info');

        expect($buyerInfo)->not->toBeNull()
            ->and($buyerInfo['type'])->toContain('role')
            ->and($buyerInfo['type'])->toContain('name');
    });

    test('resolves closure with switch cases into union type', function () {
        $statusLabel = collect($this->analysis->properties)->firstWhere('name', 'status_label');

        expect($statusLabel)->not->toBeNull()
            ->and($statusLabel['type'])->toContain('label');
    });

    test('resolves closure with try/catch/finally — all numeric branches deduplicate to single shape', function () {
        $safeTotal = collect($this->analysis->properties)->firstWhere('name', 'safe_total');

        expect($safeTotal)->not->toBeNull()
            ->and($safeTotal['type'])->toBe('{ amount: number }')
            ->and($safeTotal['optional'])->toBeTrue();
    });

    test('resolves closure with foreach — null literal in array value becomes null type not unknown', function () {
        $tags = collect($this->analysis->properties)->firstWhere('name', 'tags');

        expect($tags)->not->toBeNull()
            ->and($tags['type'])->toBe('{ first_item: string } | { first_item: null }')
            ->and($tags['optional'])->toBeTrue();
    });

    test('resolves closure with do-while loop', function () {
        $retryResult = collect($this->analysis->properties)->firstWhere('name', 'retry_result');

        expect($retryResult)->not->toBeNull()
            ->and($retryResult['type'])->toContain('attempted');
    });

    test('all top-level properties are present', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('buyer_info')
            ->and($names)->toContain('status_label')
            ->and($names)->toContain('safe_total')
            ->and($names)->toContain('tags')
            ->and($names)->toContain('retry_result');
    });
});

describe('ResourceAstAnalyzer with MergeClosureResource (resolveClosureReturnExpression with Closure)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(MergeClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('merge with closure resolves to the data array, not the guard clause', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('user_name')
            ->and($names)->toContain('user_email');
    });

    test('explicit properties alongside merge closure are present', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id');
    });
});

describe('ResourceAstAnalyzer with ControlFlowReturnResource (union multiple return branches)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ControlFlowReturnResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('includes properties from all if/elseif/else branches', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('archived')
            ->and($names)->toContain('draft')
            ->and($names)->toContain('total')
            ->and($names)->toContain('status');
    });

    test('property in all branches is required', function () {
        $id = collect($this->analysis->properties)->firstWhere('name', 'id');

        expect($id['optional'])->toBeFalse();
    });

    test('properties in only some branches are optional', function () {
        $archived = collect($this->analysis->properties)->firstWhere('name', 'archived');
        $draft = collect($this->analysis->properties)->firstWhere('name', 'draft');
        $total = collect($this->analysis->properties)->firstWhere('name', 'total');
        $status = collect($this->analysis->properties)->firstWhere('name', 'status');

        expect($archived['optional'])->toBeTrue()
            ->and($draft['optional'])->toBeTrue()
            ->and($total['optional'])->toBeTrue()
            ->and($status['optional'])->toBeTrue();
    });

    test('resolved types default to unknown at AST level', function () {
        $props = collect($this->analysis->properties);

        expect($props->firstWhere('name', 'id')['type'])->toBe('number')
            ->and($props->firstWhere('name', 'archived')['type'])->toBe('boolean')
            ->and($props->firstWhere('name', 'draft')['type'])->toBe('boolean')
            ->and($props->firstWhere('name', 'total')['type'])->toBe('number')
            ->and($props->firstWhere('name', 'status')['type'])->toBe('OrderStatusType');
    });

    test('mergeReturnBranches carries an enum referenced only inside an inline array literal', function () {
        expect($this->analysis->inlineEnumResourceFqcns)->toHaveKey('inline_enum_branch')
            ->and($this->analysis->inlineEnumResourceFqcns['inline_enum_branch'])->toContain(PaymentMethod::class);
    });
});

describe('ResourceAstAnalyzer with LoopReturnResource (collectDirectReturns loop)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(LoopReturnResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('collects return arrays from inside loop bodies and unions with fallback', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('first_item_name')
            ->and($names)->toContain('total');
    });

    test('branch-specific properties from loop are optional', function () {
        $firstName = collect($this->analysis->properties)->firstWhere('name', 'first_item_name');
        $total = collect($this->analysis->properties)->firstWhere('name', 'total');

        expect($firstName['optional'])->toBeTrue()
            ->and($total['optional'])->toBeTrue();
    });

    test('property in all branches from loop is required', function () {
        $id = collect($this->analysis->properties)->firstWhere('name', 'id');

        expect($id['optional'])->toBeFalse();
    });

    test('resolved types are correct for each property', function () {
        $props = collect($this->analysis->properties);

        expect($props->firstWhere('name', 'id')['type'])->toBe('number')
            ->and($props->firstWhere('name', 'first_item_name')['type'])->toBe('string')
            ->and($props->firstWhere('name', 'total')['type'])->toBe('number');
    });
});

describe('ResourceAstAnalyzer with ClosureUnionMetadataResource (closure union FQCNs)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ClosureUnionMetadataResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('enum FQCNs from closure union branches are propagated', function () {
        expect($this->analysis->directEnumFqcns)->toContain(OrderStatus::class);
    });

    test('resource FQCNs from closure union branches are propagated', function () {
        expect(array_values($this->analysis->nestedResources))
            ->toContain(TagResource::class);
    });

    test('related model method call inside whenLoaded closure resolves type', function () {
        $userTitled = collect($this->analysis->properties)->firstWhere('name', 'user_titled');

        expect($userTitled)->not->toBeNull()
            ->and($userTitled['type'])->toBe('string');
    });

    test('all top-level properties are present', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('status_or_null')
            ->and($names)->toContain('nested_or_null')
            ->and($names)->toContain('user_titled')
            ->and($names)->toContain('detail_or_null')
            ->and($names)->toContain('items_or_null');
    });
});

describe('ResourceAstAnalyzer with InlineArrayFqcnResource (inline array embedded FQCNs)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(InlineArrayFqcnResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('resource FQCNs from inline arrays in closures are propagated', function () {
        expect(array_values($this->analysis->nestedResources))
            ->toContain(AddressResource::class);
    });

    test('payload property is present and optional', function () {
        $payload = collect($this->analysis->properties)->firstWhere('name', 'payload');

        expect($payload)->not->toBeNull()
            ->and($payload['optional'])->toBeTrue();
    });

    test('the inline-array reference names the interface AddressResource is published as', function () {
        $payload = collect($this->analysis->properties)->firstWhere('name', 'payload');

        // AddressResource carries #[TsResource(name: 'Address')], so the reference must agree with
        // the published interface name; the class basename would name a type nothing declares.
        $published = (new ResourceTransformer(AddressResource::class))->resourceName;

        expect($published)->toBe('Address')
            ->and($payload['type'])->toContain("address: {$published};")
            ->and($payload['type'])->not->toContain('AddressResource');
    });
});

describe('ResourceAstAnalyzer with MergeMultiBranchClosureResource (multi-return merge closure)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(MergeMultiBranchClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('includes properties from all merge closure branches', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id')
            ->and($names)->toContain('archived_at')
            ->and($names)->toContain('total')
            ->and($names)->toContain('currency');
    });

    test('merge closure branch-specific properties are optional', function () {
        $archivedAt = collect($this->analysis->properties)->firstWhere('name', 'archived_at');
        $total = collect($this->analysis->properties)->firstWhere('name', 'total');
        $currency = collect($this->analysis->properties)->firstWhere('name', 'currency');

        expect($archivedAt['optional'])->toBeTrue()
            ->and($total['optional'])->toBeTrue()
            ->and($currency['optional'])->toBeTrue();
    });

    test('explicit property outside merge closure is required', function () {
        $id = collect($this->analysis->properties)->firstWhere('name', 'id');

        expect($id['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer with CommentResource — nullsafe chains and closure annotation', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(CommentResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Comment::class))->analyze();
    });

    // closure return-type annotation ──────────────────────────────

    test('body resolution wins over annotation — user_name is string not nullable', function () {
        // `fn (): ?string => $this->user->name` — body resolves to string; annotation is only a fallback
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_name');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    test('non-nullsafe chain traversal — user_email resolves to string via body', function () {
        // `fn (): ?string => $this->resource->user->email` — the chain body wins over the ?string annotation.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_email');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    test('annotation fallback fires when body is a FuncCall — user_email_annotated is string|null', function () {
        // `fn (): ?string => json_decode(...)` — json_decode() returns mixed, so the ?string annotation applies.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_email_annotated');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('no annotation and unresolvable body — unresolvable_status is unknown', function () {
        // `fn () => json_decode(...)` — json_decode() returns mixed and there is no return annotation.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'unresolvable_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('unknown')
            ->and($prop['optional'])->toBeTrue();
    });

    test('enum annotation fallback resolves type and FQCN — resolvable_status is StatusType', function () {
        // `fn (): Status => json_decode(...)` — the unresolvable body falls back to the Status annotation.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'resolvable_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeTrue()
            ->and($this->analysis->directEnumFqcns['resolvable_status'])->toBe('Workbench\\App\\Enums\\Status');
    });

    // nullsafe chains inside whenLoaded closures ──────────────────

    test('nullsafe chain in closure skips proxy step — user_name_nullable is string|null', function () {
        // `fn (): ?string => $this->user?->name` — proxy $this->user is skipped; resolves name on User
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_name_nullable');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('nullsafe chain in closure skips resource wrapper and proxy — user_email_nullable is string|null', function () {
        // `fn (): ?string => $this->resource->user?->email` — skips resource and proxy; resolves email on User
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_email_nullable');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    // top-level nullsafe chains ───────────────────────────────────

    test('top-level single-hop nullsafe enum — user_role is RoleType|null', function () {
        // `$this->user?->role` — relation user traversed; role resolved via enum cast
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_role');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('RoleType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('top-level single-hop nullsafe enum — directEnumFqcn set for user_role', function () {
        expect($this->analysis->directEnumFqcns)->toHaveKey('user_role')
            ->and($this->analysis->directEnumFqcns['user_role'])->toBe(Role::class);
    });

    test('top-level nullsafe skips resource wrapper — user_profile is Profile|null', function () {
        // `$this->resource->user?->profile` — resource wrapper skipped; user relation traversed; profile is relation
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_profile');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('Profile | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('top-level nullsafe skips resource wrapper — modelFqcn set for user_profile', function () {
        expect($this->analysis->modelFqcns)->toHaveKey('user_profile')
            ->and($this->analysis->modelFqcns['user_profile'])->toBe(Profile::class);
    });

    test('top-level multi-hop nullsafe attribute — user_profile_bio is string|null', function () {
        // `$this->user?->profile?->bio` — user relation, then profile relation, then bio attribute
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_profile_bio');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('top-level multi-hop nullsafe attribute — user_profile_avatar_url is string|null', function () {
        // `$this->resource->user?->profile?->avatar_url` — skips resource; user→profile→avatar_url
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_profile_avatar_url');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeFalse();
    });

    // plain and nullsafe chain traversal inside whenLoaded — $this->post —————————

    test('plain chain in whenLoaded closure — post_title resolves to string', function () {
        // `fn () => $this->post->title` — the proxy step $this->post is skipped via closureRelationModelClass.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_title');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    test('nullsafe chain in whenLoaded closure — post_content is string|null', function () {
        // `fn () => $this->post?->content` — proxy step skipped; ?-> appends | null
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_content');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('nullsafe accessor in whenLoaded closure — post_title_display is string|null', function () {
        // `fn () => $this->post?->title_display` — proxy step skipped; accessor returns string|null
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_title_display');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('mixed chain in whenLoaded closure — post_author is string|null', function () {
        // `fn () => $this->post->author?->name` — proxy step skipped; traverses author relation then resolves name
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_author');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    // same chains via $this->resource ———————————————————————

    test('resource wrapper skipped — post_resource_title resolves to string', function () {
        // `fn () => $this->resource->post->title` — resource wrapper and proxy step skipped
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_resource_title');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    test('resource wrapper skipped — post_resource_content is string|null', function () {
        // `fn () => $this->resource?->post?->content` — resource wrapper and proxy step skipped
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_resource_content');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('resource wrapper skipped — post_resource_title_display is string|null', function () {
        // `fn () => $this->resource->post?->title_display` — resource wrapper and proxy step skipped
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_resource_title_display');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('resource wrapper skipped — post_resource_author is string|null', function () {
        // `fn () => $this->resource->post->author?->name` — resource wrapper and proxy step skipped
        $prop = collect($this->analysis->properties)->firstWhere('name', 'post_resource_author');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    // $this->resource chains match $this-> chains ———————————————

    test('resource-wrapped chains resolve identically to direct chains', function () {
        $props = collect($this->analysis->properties)->keyBy('name');

        expect($props['post_resource_title']['type'])->toBe($props['post_title']['type'])
            ->and($props['post_resource_content']['type'])->toBe($props['post_content']['type'])
            ->and($props['post_resource_title_display']['type'])->toBe($props['post_title_display']['type'])
            ->and($props['post_resource_author']['type'])->toBe($props['post_author']['type']);
    });
});

describe('ResourceAstAnalyzer with ToArrayCastsResource — #[TsCasts] on toArray() method', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ToArrayCastsResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, User::class))->analyze();
    });

    test('overrides existing property type — role becomes string', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'role');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });

    test('overrides type and sets optional flag — email becomes string|null and optional', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'email');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('injects property not present in return array — injected_field added', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'injected_field');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('Record<string, unknown>')
            ->and($prop['optional'])->toBeFalse();
    });

    test('registers custom import from method-level TsCasts', function () {
        expect($this->analysis->customImports)->toHaveKey('@/types/geo')
            ->and($this->analysis->customImports['@/types/geo'])->toContain('GeoPoint');
    });

    test('unoverridden properties remain unaffected — id is number - name is string', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'id');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('number');

        $prop = collect($this->analysis->properties)->firstWhere('name', 'name');
        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string');
    });
});

describe('ResourceAstAnalyzer with PostCollection (#[Collects] attribute, no toArray)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(PostCollection::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection))->analyze();
    });

    test('produces data property with PostResource[] type', function () {
        $data = collect($this->analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('PostResource[]')
            ->and($data['optional'])->toBeFalse();
    });

    test('tracks PostResource FQCN in nestedResources under data key', function () {
        expect($this->analysis->nestedResources)
            ->toHaveKey('data')
            ->and($this->analysis->nestedResources['data'])->toBe(PostResource::class);
    });

    test('flatTypeAlias is null (collection wraps data in key)', function () {
        expect($this->analysis->flatTypeAlias)->toBeNull();
    });
});

describe('ResourceAstAnalyzer with PostFlatCollection ($wrap = null, no toArray)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(PostFlatCollection::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection))->analyze();
    });

    test('has flatTypeAlias set to PostResource[]', function () {
        expect($this->analysis->flatTypeAlias)->toBe('PostResource[]');
    })->skip(fn () => ! version_compare(app()->version(), '13', '>='));

    test('has flatTypeAliasFqcn pointing to PostResource', function () {
        expect($this->analysis->flatTypeAliasFqcn)->toBe(PostResource::class);
    })->skip(fn () => ! version_compare(app()->version(), '13', '>='));

    test('has no properties (type alias skips interface shape)', function () {
        expect($this->analysis->properties)->toBeEmpty();
    });
});

describe('ResourceAstAnalyzer with PreserveKeysCollection (#[PreserveKeys] attribute)', function () {
    test('emits a keyed record for a collection carrying #[PreserveKeys]', function () {
        $reflection = new ReflectionClass(PreserveKeysCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();

        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('Record<string, TeamResource>');
    })->skip(
        ! class_exists('Illuminate\Http\Resources\Attributes\PreserveKeys'),
        'PreserveKeys attribute requires Laravel 13+',
    );
});

describe('ResourceAstAnalyzer with PreserveKeysPropertyCollection ($preserveKeys property)', function () {
    test('emits a keyed record for a collection setting $preserveKeys', function () {
        $reflection = new ReflectionClass(PreserveKeysPropertyCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();

        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('Record<string, TeamResource>');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// TernaryResource — ternary operator support
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer ternary operator — enum branches', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(TernaryResource::class);
        $this->analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $this->analysis = $this->analyzer->analyze();
    });

    test('EnumResource::make vs null resolves to StatusType | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('EnumResource::make vs null stores enumFqcn for AsEnum rewrite', function () {
        expect($this->analysis->enumResources)->toHaveKey('status_or_null');
    });

    test('EnumResource::make vs EnumResource::make (same) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_or_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('EnumResource::make vs EnumResource::make (same) stores enumFqcn for AsEnum rewrite', function () {
        expect($this->analysis->enumResources)->toHaveKey('status_or_status');
    });

    test('EnumResource::make vs EnumResource::make (different) resolves to union', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_or_visibility');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('StatusType')
            ->and($prop['type'])->toContain('VisibilityType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('EnumResource::make vs EnumResource::make (different) stores multiEnumResourceFqcns for per-token AsEnum rewrite', function () {
        expect($this->analysis->multiEnumResourceFqcns)->toHaveKey('status_or_visibility');

        $fqcns = $this->analysis->multiEnumResourceFqcns['status_or_visibility'];
        expect($fqcns)->toHaveCount(2)
            ->and($fqcns[0])->toBe(Status::class)
            ->and($fqcns[1])->toBe(Visibility::class);
    });

    test('EnumResource::make vs $this->prop (same enum) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_resource_or_type');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('EnumResource::make vs $this->prop (same enum) stores both enumFqcn and directEnumFqcn for mixed rewrite', function () {
        expect($this->analysis->enumResources)->toHaveKey('status_resource_or_type')
            ->and($this->analysis->directEnumFqcns)->toHaveKey('status_resource_or_type');
    });
});

describe('ResourceAstAnalyzer ternary operator — resource branches', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(TernaryResource::class);
        $this->analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $this->analysis = $this->analyzer->analyze();
    });

    test('Resource::make vs null resolves to CategoryResource | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('Resource::make vs Resource::make (same) resolves to CategoryResource', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_or_category');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource')
            ->and($prop['optional'])->toBeFalse();
    });

    test('Resource::make vs Resource::make (different) resolves to union type', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_or_user');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('CategoryResource')
            ->and($prop['type'])->toContain('UserResource')
            ->and($prop['optional'])->toBeFalse();
    });

    test('new Resource() vs null resolves to ImageResource | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'image_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('ImageResource | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('Resource::collection vs null resolves to CommentResource[] | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'comments_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CommentResource[] | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('Resource::collection vs Resource::collection (same) resolves to CommentResource[]', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'comments_or_comments');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CommentResource[]')
            ->and($prop['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer ternary operator — scalar branches', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(TernaryResource::class);
        $this->analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $this->analysis = $this->analyzer->analyze();
    });

    test('string property vs null resolves to string | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'title_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('number property vs null resolves to number | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'word_count_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('number | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('string literal vs string literal resolves to string', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'pin_label');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });

    test('Elvis operator with string fallback resolves to string', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'title_fallback');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });
});

describe('ResourceAstAnalyzer ternary operator — conditional / closure contexts', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(TernaryResource::class);
        $this->analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $this->analysis = $this->analyzer->analyze();
    });

    test('ternary inside whenLoaded closure is optional', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_when_loaded_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('CategoryResource')
            ->and($prop['optional'])->toBeTrue();
    });

    test('ternary using $this->resource accessor resolves to CategoryResource | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_resource_or_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CategoryResource | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('nested ternary resolves to string | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'nested_ternary_label');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// conditional closure param type resolution
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ConditionalParamEnumResource — issue #38 enum param binding', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamEnumResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // $this->when($this->status, fn ($status) => ...) — $status binds to $this->status, an OrderStatus enum.
    test('when() param → EnumResource::make($status) resolves to OrderStatusType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_resource');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('OrderStatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    // $this->when($this->status, fn ($status) => $status) — the enum is returned bare.
    test('when() param → bare $status return resolves to OrderStatusType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_bare');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('OrderStatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    // $this->when($this->currency, fn ($currency) => ...) — $currency is the Currency enum on Order.
    test('when() param → EnumResource::make($currency) resolves to CurrencyType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'currency_resource');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CurrencyType')
            ->and($prop['optional'])->toBeTrue();
    });

    // $this->whenLoaded('user', fn ($user) => ...) — $user is the User relation; $user->role is the Role enum.
    test('whenLoaded() param → EnumResource::make($user->role) resolves to RoleType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_role');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('RoleType')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with ConditionalParamMappedResource — issue #38 map() param binding', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamMappedResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // Outer param $items binds to the items collection; the inner typed param $item to OrderItem.
    test('whenLoaded() param → items->map() with typed inner closure resolves to array shape not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'items_mapped');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ id: number; name: string; quantity: number }[]')
            ->and($prop['optional'])->toBeTrue();
    });

    test('whenLoaded() param → items->map() with price fields resolves to array shape not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'items_priced');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ id: number; sku: string; unit_price: number; total_price: number }[]')
            ->and($prop['optional'])->toBeTrue();
    });

    test('whenLoaded() param → items->pluck() resolves to non-unknown type', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'item_names');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string[]')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with ConditionalParamArrayResource — issue #38 coalesce resolution', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamArrayResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // fn () => $this->notes ?? 'none' — notes is string|null and ?? strips the null.
    test('when() with coalesce (??) resolves to string not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_or_default');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    // whenNull($this->notes, fn () => 'no notes') — the success arm proves the value null, and the
    // default is a genuine explicit default, so the key is required and unions null with the arrow fn's
    // string return.
    test('whenNull() arrow fn → unions null with the string default, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_when_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('null | string')
            ->and($prop['optional'])->toBeFalse();
    });

    // flagged_notes_present: top-level null stripped, element-level null kept, no default → optional
    test('whenNotNull() on a nested-nullable union strips only the top-level null arm', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'flagged_notes_present');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('(string | null)[]')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with ConditionalParamFullClosureResource — issue #38 full closure params', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamFullClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // function ($items) { return $items->map(function (OrderItem $item) { ... }); }
    test('full closure param → items->map() with typed inner closure resolves to array shape not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'items_mapped');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ id: number; name: string; quantity: number }[]')
            ->and($prop['optional'])->toBeTrue();
    });

    // Policy pin: the default closure declares a required $status param, so value($default) invoking it
    // with zero args would throw — the arity rule excludes the default arm as unreachable before body
    // analysis runs, leaving the value arm's own type standing, correct by evidence, still required.
    test('full closure param → EnumResource::make keeps its type, still required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_resource');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('OrderStatusType')
            ->and($prop['type'])->not->toContain('unknown')
            ->and($prop['optional'])->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// whenNotNull()'s default arm is analyzed on its own, never as a callback bound to the value —
// ConditionalParamPrimitiveResource
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ConditionalParamPrimitiveResource — whenNotNull default-arm resolution', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamPrimitiveResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // whenNotNull($this->notes, fn ($notes) => strlen($notes)) declares a required $notes param, but Laravel
    // invokes every conditional default via value($default) with zero arguments — an ArgumentCountError at
    // runtime. The analyzer excludes this arm as unreachable, leaving the value arm (string) standing alone.
    test('whenNotNull() default arm requiring a parameter is unreachable — string alone, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_length');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeFalse();
    });

    // whenNotNull($this->notes, fn ($notes = '') => strlen($notes)) — the default closure's parameter has
    // its own default, so value($default) invoking it with zero args still runs cleanly. Its arm must still
    // union in: string (from notes) | number (from strlen), required.
    test('whenNotNull() default arm with an optional parameter still invokes cleanly — string | number, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_length_or_default');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | number')
            ->and($prop['optional'])->toBeFalse();
    });

    // whenNotNull($this->notes, fn (...$args) => 1) — a variadic-only parameter accepts zero or more
    // arguments, so value($default) invoking it with zero args still runs cleanly. Its arm must still
    // union in: string (from notes) | number (from the literal), required.
    test('whenNotNull() default arm with a variadic parameter still invokes cleanly — string | number, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_length_variadic_default');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | number')
            ->and($prop['optional'])->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// whenNotNull()/whenNull() default-argument handling — ConditionalDefaultsResource
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ConditionalDefaultsResource — whenNotNull/whenNull default argument', function () {
    it('types whenNotNull from the value argument with null stripped', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['not_null_no_default']['type'])->toBe('string')
            ->and($props['not_null_no_default']['optional'])->toBeTrue();
    });

    it('unions the default arm into whenNotNull and makes it required', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['not_null_with_default']['type'])->toBe('string | number')
            ->and($props['not_null_with_default']['optional'])->toBeFalse();
    });

    it('collapses a same-typed default to a single type', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['not_null_same_type_default']['type'])->toBe('number')
            ->and($props['not_null_same_type_default']['optional'])->toBeFalse();
    });

    // hasExplicitDefaultArg() contract — pinned per brief: position is the only signal, since Laravel
    // distinguishes an omitted argument from an explicitly-passed `null` via func_num_args(), not `=== null`.
    it('treats an explicit null at the default position as a real default', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['not_null_explicit_null_default']['type'])->toBe('string | null')
            ->and($props['not_null_explicit_null_default']['optional'])->toBeFalse();
    });

    // Laravel's func_num_args() counts `default: 0` as a second argument, so a named default is a real
    // default: it unions in and makes the key required, exactly like the positional form above.
    it('reads a named default argument as a real default, required and unioned', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['not_null_named_default']['type'])->toBe('string | number')
            ->and($props['not_null_named_default']['optional'])->toBeFalse();
    });

    // A spread argument at the default position makes the count unknowable, so it bails out.
    it('bails out on a spread default argument, back to value-arm-only and optional', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['not_null_spread_default']['type'])->toBe('string')
            ->and($props['not_null_spread_default']['optional'])->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Conditional-family default argument makes the property required — ConditionalDefaultsResource
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ConditionalDefaultsResource — explicit default makes conditional required', function () {
    it('makes a conditional required when an explicit default is passed', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['when_no_default']['optional'])->toBeTrue()
            ->and($props['when_with_default']['optional'])->toBeFalse()
            ->and($props['has_with_default']['optional'])->toBeFalse()
            ->and($props['counted_with_default']['optional'])->toBeFalse();
    });

    // The explicit `null` is what makes this discriminating twice over: it must count as a real default
    // (required, not optional), and its own `null` must reach the type — emitting a bare `User` would let a
    // consumer dereference the very null Laravel returns when the relation is not loaded.
    it('treats an explicit null default as a real default and unions its null in', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['loaded_with_default']['optional'])->toBeFalse()
            ->and($props['loaded_with_default']['type'])->toBe('User | null');
    });

    // A same-typed default can't distinguish real union logic from a no-op, since deduping collapses either
    // result back to a single member. Every default below is deliberately typed differently from its value
    // arm, so a handler that merely flipped `optional` would emit the value arm alone and redden here.
    it('unions the default arm into the emitted type across the whole conditional family', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['when_with_default']['type'])->toBe('string | number');
        expect($props['has_with_default']['type'])->toBe('string | number');
        expect($props['counted_with_default']['type'])->toBe('number | string');
        expect($props['aggregated_with_default']['type'])->toBe('number | string');
        expect($props['appended_with_default']['type'])->toBe('string | number');
        expect($props['exists_with_default']['type'])->toBe('boolean | string');
        expect($props['unless_with_default']['type'])->toBe('string | number');
    });

    // whenAggregated's default sits at index 4 — the family's outlier index, verified against
    // ConditionallyLoadsAttributes.php. Neither of the two states alone would catch an off-by-one at this
    // index; asserting both together (rather than only the "with default" case) is what makes this
    // discriminating.
    it('makes whenAggregated required only when its index-4 default is passed', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['aggregated_no_default']['optional'])->toBeTrue()
            ->and($props['aggregated_with_default']['optional'])->toBeFalse();
    });

    // whenPivotLoaded's default sits at index 2. Its value arm is a hard-coded `unknown` that already
    // covers the default, so the union collapses back to `unknown` — narrowing to the default's `number`
    // would claim a type for a pivot value the analyzer never inspected.
    it('makes whenPivotLoaded required only when its index-2 default is passed, still unknown', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['pivot_loaded_no_default']['optional'])->toBeTrue()
            ->and($props['pivot_loaded_with_default']['optional'])->toBeFalse()
            ->and($props['pivot_loaded_with_default']['type'])->toBe('unknown');
    });

    // whenPivotLoadedAs's default sits at index 3 — one higher than whenPivotLoaded, because of its
    // leading $accessor argument. This is exactly the pair the combined `if` branch used to conflate.
    it('makes whenPivotLoadedAs required only when its index-3 default is passed', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['pivot_loaded_as_no_default']['optional'])->toBeTrue()
            ->and($props['pivot_loaded_as_with_default']['optional'])->toBeFalse()
            ->and($props['pivot_loaded_as_with_default']['type'])->toBe('unknown');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// unless()/whenAppended()/whenExistsLoaded()/transform() — previously unhandled conditionals that
// fell through to a required `unknown` before this coverage was added.
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ConditionalDefaultsResource — unless/whenAppended/whenExistsLoaded/transform', function () {
    // full_address resolves as 'string' (not 'string | null') through ModelAttributeResolver's
    // accessor-return-type inference here, same as the existing whenHas('full_address', ...) coverage —
    // #[TsCasts]'s 'string | null' declaration governs the model's own generated interface, not this path.
    it('types unless like when, and never as a required unknown', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['unless_no_default']['type'])->toBe('string')
            ->and($props['unless_no_default']['optional'])->toBeTrue();

        expect($props['unless_with_default']['optional'])->toBeFalse();
    });

    it('types whenAppended from the named attribute', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['appended_no_default']['type'])->toBe('string')
            ->and($props['appended_no_default']['optional'])->toBeTrue()
            ->and($props['appended_with_default']['optional'])->toBeFalse();
    });

    it('types whenExistsLoaded as a boolean-ish existence flag', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['exists_no_default']['optional'])->toBeTrue()
            ->and($props['exists_no_default']['type'])->not->toBe('unknown')
            ->and($props['exists_with_default']['optional'])->toBeFalse();
    });

    // transform()'s default sits at index 2, and types from the callback's return, not $value's — the
    // callback here returns boolean while $value (full_address) resolves as string, so a wrong
    // implementation would leak string through instead.
    it('types transform from the callback return and unions a differently-typed default', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['transform_no_default']['type'])->toBe('boolean')
            ->and($props['transform_no_default']['optional'])->toBeTrue();

        expect($props['transform_with_default']['type'])->toBe('boolean | number')
            ->and($props['transform_with_default']['optional'])->toBeFalse();
    });

    // transform()'s default is invoked via the global transform() helper's $default($value) — one
    // argument — unlike the rest of the family's zero-argument value($default). A one-parameter closure
    // default therefore runs cleanly and must union in, not be treated as unreachable.
    it('unions a one-parameter transform() default instead of excluding it as unreachable', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['transform_with_one_param_default']['type'])->toBe('boolean | number')
            ->and($props['transform_with_one_param_default']['optional'])->toBeFalse();
    });

    // unless/transform were absent from $conditionalMethods, the list a nested resource constructor
    // consults, so wrapping one in either emitted a required property instead of optional. StaticCall
    // (::make()) and New_ (new Resource()) take separate detection paths, so both need coverage.
    it('marks a nested resource constructor wrapping unless/transform as optional', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['unless_user_resource']['type'])->toBe('UserResource')
            ->and($props['unless_user_resource']['optional'])->toBeTrue();

        expect($props['transform_user_resource']['type'])->toBe('UserResource')
            ->and($props['transform_user_resource']['optional'])->toBeTrue();
    });

    // mergeUnless mirrors mergeWhen: array/closure argument at index 1, always optional. If the dispatch
    // string or operator were wrong, analyzeMergeExpression() would return an empty ResourceAnalysis and
    // this key would be silently missing from the output entirely — not typed 'unknown', just absent.
    it('resolves mergeUnless properties as optional, mirroring mergeWhen', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ConditionalDefaultsResource::class), Address::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props->has('merge_unless_label'))->toBeTrue()
            ->and($props['merge_unless_label']['type'])->toBe('string')
            ->and($props['merge_unless_label']['optional'])->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ResourceWrappedEnumResource — issue #43: $this->resource->prop enum resolution
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ResourceWrappedEnumResource — issue #43 $this->resource->prop', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ResourceWrappedEnumResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Post::class))->analyze();
    });

    // ── Direct access ──────────────────────────────────────────────────────────

    test('EnumResource::make($this->resource->status) resolves to StatusType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_make');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('new EnumResource($this->resource->status) resolves to StatusType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_new');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('EnumResource::make($this->resource->visibility) resolves to VisibilityType|null not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'visibility_make');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('VisibilityType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('new EnumResource($this->resource->priority) resolves to PriorityType|null not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'priority_new');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('PriorityType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    // ── when() ─────────────────────────────────────────────────────────────────

    test('when() pre-evaluated EnumResource::make($this->resource->status) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_when_make');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    test('when() arrow fn → EnumResource::make($this->resource->status) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_when_arrow');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    test('when() full closure → new EnumResource($this->resource->visibility) resolves to VisibilityType|null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'visibility_when_full');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('VisibilityType | null')
            ->and($prop['optional'])->toBeTrue();
    });

    // ── whenNotNull() ──────────────────────────────────────────────────────────
    // Each of these passes a genuine second argument, so it's a real default: the key is always
    // present (optional: false), unlike the single-arg whenNotNull() calls tested elsewhere.

    test('whenNotNull() pre-evaluated EnumResource::make($this->resource->priority) resolves to PriorityType|null, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'priority_when_not_null_make');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('PriorityType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('whenNotNull() arrow fn → EnumResource::make($this->resource->status) resolves to StatusType, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_when_not_null_arrow');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('whenNotNull() full closure → new EnumResource($this->resource->visibility) resolves to VisibilityType|null, required', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'visibility_when_not_null_full');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('VisibilityType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    // ── Ternary ────────────────────────────────────────────────────────────────

    test('ternary: EnumResource::make($this->resource->status) vs null resolves to StatusType | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_ternary_null');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    test('ternary: both branches same enum via $this->resource-> resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_ternary_both');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeFalse();
    });

    test('ternary: two different enum types via $this->resource-> resolves to StatusType | VisibilityType | null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_or_visibility_ternary');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType | VisibilityType | null')
            ->and($prop['optional'])->toBeFalse();
    });

    // A same-shaped mixed ternary (EnumResource::make() vs a direct read, both scalar StatusType)
    // dedupes to one merged token, so expandMixedEnumType() spells the wrapped arm out beside the
    // bare one — rewriting the single token in place would drop the direct-read arm.
    test('mixed ternary nested in an inline array: same-shaped arms both stay named in the union', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'ternary_enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ status: AsEnum<typeof Status> | StatusType }');
    });

    // ── Inline array: enums_array (all EnumResource) ──────────────────────────

    test('inline array with only EnumResource::make() values produces AsEnum types when tolki enabled', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ status: AsEnum<typeof Status>; visibility: AsEnum<typeof Visibility> | null; priority: AsEnum<typeof Priority> | null }');
    });

    test('inline array with only EnumResource::make() values produces plain types when tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $reflection = new ReflectionClass(ResourceWrappedEnumResource::class);
        $analysis = (new ResourceAstAnalyzer($reflection, Post::class))->analyze();
        $prop = collect($analysis->properties)->firstWhere('name', 'enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ status: StatusType; visibility: VisibilityType | null; priority: PriorityType | null }');
    });

    test('inline array with only EnumResource values records FQCNs in inlineEnumResourceFqcns', function () {
        expect($this->analysis->inlineEnumResourceFqcns)->toHaveKey('enums_array')
            ->and($this->analysis->inlineEnumResourceFqcns['enums_array'])->toContain(Status::class)
            ->and($this->analysis->inlineEnumResourceFqcns['enums_array'])->toContain(Visibility::class)
            ->and($this->analysis->inlineEnumResourceFqcns['enums_array'])->toContain(Priority::class);
    });

    // ── Inline array: mixed_enums_array (direct + EnumResource) ───────────────

    test('mixed inline array: direct $this->prop enum access produces plain type', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'mixed_enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('status_type: StatusType')
            ->and($prop['type'])->toContain('visibility_type: VisibilityType | null')
            ->and($prop['type'])->toContain('priority_type: PriorityType | null');
    });

    test('mixed inline array: $this->resource->prop direct access produces plain type', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'mixed_enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('status_resource_type: StatusType')
            ->and($prop['type'])->toContain('visibility_resource_type: VisibilityType | null')
            ->and($prop['type'])->toContain('priority_resource_type: PriorityType | null');
    });

    test('mixed inline array: EnumResource::make($this->resource->prop) produces AsEnum types when tolki enabled', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'mixed_enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('status_enum: AsEnum<typeof Status>')
            ->and($prop['type'])->toContain('visibility_enum: AsEnum<typeof Visibility> | null')
            ->and($prop['type'])->toContain('priority_enum: AsEnum<typeof Priority> | null');
    });

    test('mixed inline array: EnumResource::make($this->resource->prop) produces plain type when tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $reflection = new ReflectionClass(ResourceWrappedEnumResource::class);
        $analysis = (new ResourceAstAnalyzer($reflection, Post::class))->analyze();
        $prop = collect($analysis->properties)->firstWhere('name', 'mixed_enums_array');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toContain('status_enum: StatusType')
            ->and($prop['type'])->not->toContain('AsEnum');
    });

    test('mixed inline array: enum resource FQCNs appear in inlineEnumResourceFqcns', function () {
        expect($this->analysis->inlineEnumResourceFqcns)->toHaveKey('mixed_enums_array')
            ->and($this->analysis->inlineEnumResourceFqcns['mixed_enums_array'])->toContain(Status::class)
            ->and($this->analysis->inlineEnumResourceFqcns['mixed_enums_array'])->toContain(Visibility::class)
            ->and($this->analysis->inlineEnumResourceFqcns['mixed_enums_array'])->toContain(Priority::class);
    });

    test('mixed inline array: direct enum FQCNs appear in inlineEnumFqcns', function () {
        expect($this->analysis->inlineEnumFqcns)->toHaveKey('mixed_enums_array')
            ->and($this->analysis->inlineEnumFqcns['mixed_enums_array'])->toContain(Status::class)
            ->and($this->analysis->inlineEnumFqcns['mixed_enums_array'])->toContain(Visibility::class)
            ->and($this->analysis->inlineEnumFqcns['mixed_enums_array'])->toContain(Priority::class);
    });

    // ── mergeWhen() ────────────────────────────────────────────────────────────

    test('mergeWhen() inline array: EnumResource::make($this->resource->status) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'merged_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    test('mergeWhen() inline array: new EnumResource($this->resource->visibility) resolves to VisibilityType|null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'merged_visibility');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('VisibilityType | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('mergeWhen() arrow closure array: EnumResource::make($this->resource->status) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'deferred_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    test('mergeWhen() arrow closure array: new EnumResource($this->resource->priority) resolves to PriorityType|null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'deferred_priority');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('PriorityType | null')
            ->and($prop['optional'])->toBeTrue();
    });

    // ── whenLoaded() ───────────────────────────────────────────────────────────

    test('whenLoaded() arrow fn: EnumResource::make($this->resource->status) resolves to StatusType', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('StatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    test('whenLoaded() full closure: new EnumResource($this->resource->visibility) resolves to VisibilityType|null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'category_visibility');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('VisibilityType | null')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('boolean expression inference', function () {
    // PHP's &&/|| return a real bool, unlike JS — even as a null-guard (`$this->x && $this->x->y`),
    // so `boolean` is right for every use and no false|T union is needed.
    test('comparison and logical operators resolve to boolean', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(BooleanExprResource::class), Order::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        foreach (['is_recent', 'is_equal', 'is_large', 'both', 'negated', 'is_order', 'has_notes', 'no_notes'] as $name) {
            expect($props[$name]['type'])->toBe('boolean', "property {$name}");
            expect($props[$name]['optional'])->toBeFalse("property {$name}");
        }
    });

    test('spaceship comparison resolves to number', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(BooleanExprResource::class), Order::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['compared']['type'])->toBe('number')
            ->and($props['compared']['optional'])->toBeFalse();
    });
});

describe('TsCasts-removability regressions', function () {
    test('(float) cast resolves to number', function () {
        // Guards against a #[TsCasts] annotation being reintroduced for a plain (float) cast.
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(BooleanExprResource::class), Order::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['price_float']['type'])->toBe('number')
            ->and($props['price_float']['optional'])->toBeFalse();
    });

    test('whenLoaded closure over nullsafe relation property resolves to string | null optional', function () {
        // Guards against a #[TsCasts] annotation being reintroduced for a whenLoaded closure: the `?string`
        // annotation is inert — the type comes from resolving `$this->user?->email` on the loaded relation.
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(BooleanExprResource::class), Order::class);
        $props = collect($analyzer->analyze()->properties)->keyBy('name');

        expect($props['user_bio']['type'])->toBe('string | null')
            ->and($props['user_bio']['optional'])->toBeTrue();
    });
});

describe('static call inference', function () {
    beforeEach(function () {
        $this->props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(StaticCallResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');
    });

    test('service static call resolves declared return type', function () {
        expect($this->props['url']['type'])->toBe('string');
    });

    test('EnumResource::make with enum static method arg resolves enum type', function () {
        expect($this->props['status_badge']['type'])->toContain('Status');
    });

    test('EnumResource::make with enum const arg resolves enum type', function () {
        expect($this->props['status_const']['type'])->toContain('Status');
    });

    test('ResourceCollection::make resolves to collected resource array', function () {
        expect($this->props['items']['type'])->toBe('OrderItemResource[]');
    });

    test('static call declared to return an enum resolves via directEnumFqcn', function () {
        // Covers acceptReflectedTypeInfo()'s general-reflection branch plumbing enumFqcns[0] as directEnumFqcn.
        expect($this->props['default_status']['type'])->toContain('Status');
    });

    test('static call declared to return a Model resolves via modelFqcn', function () {
        // A single-Model classFqcns entry now dispatches through the modelFqcn slot instead of degrading.
        expect($this->props['located_order']['type'])->toBe('Order');
    });

    test('new ResourceCollection(...) resolves to collected resource array', function () {
        expect($this->props['new_items']['type'])->toBe('OrderItemResource[]');
    });

    test('static call declared to return a #[TsType]-annotated class resolves via customImports', function () {
        // #[TsType] classes register only in customImports, now carried through on the result.
        expect($this->props['menu_settings']['type'])->toBe('MenuSettingsType');
    });

    test('static call declared to return a multi-enum union resolves via embeddedEnumFqcns', function () {
        // directEnumFqcn carries a single FQCN, so a Status|Priority union dispatches through the list slot.
        expect($this->props['status_or_priority']['type'])->toContain('StatusType')
            ->and($this->props['status_or_priority']['type'])->toContain('PriorityType');
    });

    test('static call declared void/never/mixed degrades to unknown', function () {
        foreach (['void_return', 'never_return', 'mixed_return'] as $name) {
            expect($this->props[$name]['type'])->toBe('unknown', "property {$name}");
        }
    });

    test('static call declared to return an enum-plus-model union resolves via both channels', function () {
        // classFqcns and enumFqcns can both be non-empty for one TypeScriptTypeInfo (Order|Status); both
        // must dispatch off the same result (embeddedModelFqcns and embeddedEnumFqcns) rather than one
        // guard shadowing the other.
        expect($this->props['order_or_status']['type'])->toContain('Order')
            ->and($this->props['order_or_status']['type'])->toContain('StatusType');
    });

    test('static call declared to return a non-Model class still degrades to unknown', function () {
        // A return type backed by an arbitrary, non-Model class has no dispatch path for its import
        // (no published file exists for OpaqueHandle), so the whole result is still rejected.
        expect($this->props['money_value']['type'])->toBe('unknown');
    });
});

describe('vague array signature docblock shapes resolved via $this->resource->method()', function () {
    beforeEach(function () {
        $this->props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(StaticCallResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');
    });

    test('$this->resource->asAutoCompleteOption() resolves the @return array{...} shape, not unknown[]', function () {
        expect($this->props['autocomplete']['type'])->toBe('{ value: number; label: string }');
    });

    test('$this->resource->presetSummaries() resolves list<array{...}> to an object array', function () {
        expect($this->props['summaries']['type'])->toBe('{ key: string; label: string }[]');
    });
});

describe('reflected static-call types dispatch their imports', function () {
    beforeEach(function () {
        $this->analysis = new ResourceAstAnalyzer(
            new ReflectionClass(StaticCallResource::class), Order::class,
        )->analyze();
        $this->props = collect($this->analysis->properties)->keyBy('name');
    });

    test('a model return type resolves with its FQCN plumbed', function () {
        expect($this->props['located_order']['type'])->toBe('Order')
            ->and($this->analysis->modelFqcns)->toHaveKey('located_order');
    });

    test('a TsType custom import carries through', function () {
        expect($this->props['menu_settings']['type'])->toBe('MenuSettingsType')
            ->and($this->analysis->customImports)->toHaveKey('@js/types/settings');
    });

    test('an enum union resolves with both enums plumbed', function () {
        expect($this->props['status_or_priority']['type'])->toContain('StatusType')
            ->and($this->props['status_or_priority']['type'])->toContain('PriorityType')
            ->and($this->analysis->inlineEnumFqcns)->toHaveKey('status_or_priority');
    });

    test('a model|enum union resolves with both channels plumbed', function () {
        expect($this->props['order_or_status']['type'])->toContain('Order')
            ->and($this->props['order_or_status']['type'])->toContain('StatusType');
    });

    test('a DTO return type still degrades to unknown', function () {
        expect($this->props['money_value']['type'] ?? 'unknown')->toBe('unknown');
    });

    test('a TsType custom import survives a ternary merge with a null branch', function () {
        // analyzeTernary() routes through analyzeClosureUnion(), which previously propagated every
        // other FQCN channel (modelFqcn, directEnumFqcn, embedded lists) but not customImports.
        // page_meta_ternary uses a #[TsType] class distinct from the plain menu_settings property's
        // MenuSettings, so this assertion can't pass by riding on that unrelated property's import.
        expect($this->props['page_meta_ternary']['type'])->toBe('PageMetaType | null')
            ->and($this->analysis->customImports)->toHaveKey('@js/types/page-meta')
            ->and($this->analysis->customImports['@js/types/page-meta'])->toContain('PageMetaType');
    });

    test('a TsType custom import survives a coalesce merge with a discarded left branch', function () {
        // analyzeCoalesce() previously rebuilt its result from scratch, dropping every channel; the
        // left branch here degrades to unknown and is discarded, so only the right branch's import
        // may end up in the emitted file. widget_config_coalesce uses a third distinct #[TsType]
        // class so this assertion is isolated from both menu_settings and page_meta_ternary.
        expect($this->props['widget_config_coalesce']['type'])->toBe('WidgetConfigType')
            ->and($this->analysis->customImports)->toHaveKey('@js/types/widget-config')
            ->and($this->analysis->customImports['@js/types/widget-config'])->toContain('WidgetConfigType');
    });
});

describe('helper and receiver method inference', function () {
    beforeEach(function () {
        $this->props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(HelperCallResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');
    });

    test('route() helper resolves to string', function () {
        expect($this->props['route_url']['type'])->toBe('string')
            ->and($this->props['route_url']['optional'])->toBeFalse();
    });

    test('Carbon method on datetime attribute resolves to string', function () {
        expect($this->props['ship_date']['type'])->toBe('string')
            ->and($this->props['ship_date']['optional'])->toBeFalse();
    });

    test('can() resolves to boolean', function () {
        expect($this->props['can_edit']['type'])->toBe('boolean')
            ->and($this->props['can_edit']['optional'])->toBeFalse();
    });

    test('count() on a many relation resolves to number', function () {
        expect($this->props['item_total']['type'])->toBe('number')
            ->and($this->props['item_total']['optional'])->toBeFalse();
    });

    // CarbonInterval/CarbonPeriod are Stringable but not strings — toTsType()'s __toString fallback must not fire.
    test('Carbon diff() returning CarbonInterval degrades to unknown, not string', function () {
        expect($this->props['diff_result']['type'])->toBe('unknown');
    });

    test('Carbon toPeriod() returning CarbonPeriod degrades to unknown, not string', function () {
        expect($this->props['period_result']['type'])->toBe('unknown');
    });

    // Carbon/CarbonImmutable's __toString() IS their canonical form, so the Stringable guard must skip them.
    test('Carbon toMutable() returning Carbon resolves to string, not unknown', function () {
        expect($this->props['to_mutable']['type'])->toBe('string');
    });

    test('Carbon toImmutable() returning CarbonImmutable resolves to string, not unknown', function () {
        expect($this->props['to_immutable']['type'])->toBe('string');
    });

    // getKey()'s type is receiver-dependent, unlike can()/cannot(), so it may fire only on $this->resource.
    test('getKey() on a non-$this->resource receiver stays unknown', function () {
        expect($this->props['user_key']['type'])->toBe('unknown');
    });
});

describe('local variable bindings', function () {
    beforeEach(function () {
        $this->props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(LocalVarResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');
    });

    test('assigned locals resolve through their bound expressions', function () {
        expect($this->props['label']['type'])->toBe('string')
            ->and($this->props['key']['type'])->toBe('number');
    });

    // A variable also written inside nested control flow is dropped from $localVarBindings entirely.
    test('a variable reassigned in nested control flow degrades to unknown, not the stale top-level type', function () {
        expect($this->props['shadowed']['type'])->toBe('unknown');
    });

    // A closure parameter rebinds the name: it resolves against its own bound model (whenLoaded
    // relation / chain element), and must not leak that binding into a same-named outer local.
    test('a closure or arrow-function parameter shadowing an outer local binds to its own model, and the shadowed top-level local keeps its own binding', function () {
        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(ClosureParamShadowResource::class), Team::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect($props['mapped_members']['type'])->toBe('User[]')
            ->and($props['loaded_owner']['type'])->toBe('User')
            // The shadowing closure param no longer suppresses the outer $member local's own binding.
            ->and($props['outer_member']['type'])->toBe('string');
    });

    // Guards the regression narrowing collectWrittenVariableNames() alone would introduce: with no
    // scoped binding for the closure param, the outer local must stay unknown, not leak through.
    test('does not leak an outer local into a closure that shadows its name', function () {
        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(ShadowedClosureParamResource::class), Team::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect($props['outer']['type'])->toBe('string')
            ->and($props['shadowed']['type'])->toBe('unknown');
    });

    // Order has the default int key; UuidPost covers getKey()'s string-keyed branch.
    test('getKey() resolves to string for a string-keyed model', function () {
        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(LocalVarResource::class), UuidPost::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect($props['key']['type'])->toBe('string');
    });
});

describe('variable-to-model bindings', function () {
    test('whenLoaded closure param binds to the relation target', function () {
        $props = collect((new ResourceAstAnalyzer(
            new ReflectionClass(ClosureParamShadowResource::class), Team::class,
        ))->analyze()->properties)->keyBy('name');

        expect($props['loaded_owner']['type'])->toBe('User')
            ->and($props['mapped_members']['type'])->toBe('User[]')
            ->and($props['outer_member']['type'])->toBe('string');
    });

    // 'members' is a to-many relation: the closure param holds the whole collection, not one
    // element, so a bare return resolves to the collection type `User[]` — never the singular
    // element model `User`.
    test('whenLoaded closure param binds to the collection type for a to-many relation', function () {
        $props = collect((new ResourceAstAnalyzer(
            new ReflectionClass(ClosureParamShadowResource::class), Team::class,
        ))->analyze()->properties)->keyBy('name');

        expect($props['loaded_members_bare']['type'])->toBe('User[]');
        expect($props['loaded_members_bare']['type'])->not->toBe('User');
    });

    test('relation chain first() yields the element or null', function () {
        $props = collect((new ResourceAstAnalyzer(
            new ReflectionClass(RelationChainResource::class), Team::class,
        ))->analyze()->properties)->keyBy('name');

        expect($props['first_member']['type'])->toBe('User | null');
    });

    test('foreach over a many-relation binds the loop variable', function () {
        $props = collect((new ResourceAstAnalyzer(
            new ReflectionClass(LoopReturnResource::class), Order::class,
        ))->analyze()->properties)->keyBy('name');

        expect($props['first_item_name']['type'])->toBe('string');
    });
});

describe('local variable bindings — review follow-up regressions', function () {
    // $localVarBindings from toArray() must not leak into a method reached via a `...$this->method()` spread.
    test('a spread method\'s own local var bindings do not leak from or into toArray()', function () {
        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(LocalVarSpreadResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect($props['x']['type'])->toBe('number')
            ->and($props['y']['type'])->toBe('string');
    });

    // Which of two top-level assignments is live depends on the return branch taken — no last-assignment-wins.
    test('two top-level assignments to the same var across return branches both degrade to unknown', function () {
        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(LocalVarGuardClauseResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect($props['early']['type'])->toBe('unknown')
            ->and($props['late']['type'])->toBe('unknown');
    });

    // A foreach binding, compound assignment, increment and by-ref alias are writes too, not just Assign.
    test('non-Assign reassignment forms are recognized and degrade to unknown', function () {
        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(LocalVarReassignResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect($props['via_foreach']['type'])->toBe('unknown')
            ->and($props['via_concat']['type'])->toBe('unknown')
            ->and($props['via_increment']['type'])->toBe('unknown')
            ->and($props['via_ref']['type'])->toBe('unknown');
    });

    // A regression here hangs CI rather than failing an assertion, so the wall-clock bound is the real check.
    test('mutual and self-referential local var bindings terminate instead of hanging', function () {
        $start = microtime(true);

        $props = collect(
            (new ResourceAstAnalyzer(new ReflectionClass(LocalVarRecursionResource::class), Order::class))
                ->analyze()->properties,
        )->keyBy('name');

        expect(microtime(true) - $start)->toBeLessThan(5.0)
            ->and($props['mutual']['type'])->toBe('unknown')
            ->and($props['self']['type'])->toBe('unknown');
    });
});

// Every emitted type token must arrive with a matching import; these four paths each used to drop one.

test('the customImports map survives every result collector', function () {
    // analyzeReturnArray() already merged the map; the inline-array, merge() and variable-assignment
    // collectors dropped it, so their tokens reached the file with no import at all.
    $analysis = new ResourceAstAnalyzer(
        new ReflectionClass(CustomImportChannelResource::class), Order::class,
    )->analyze();
    $props = collect($analysis->properties)->keyBy('name');

    expect($props['inline_meta']['type'])->toBe('{ cfg: MenuSettingsType }')
        ->and($props['merged_meta']['type'])->toBe('PageMetaType')
        ->and($props['assigned_meta']['type'])->toBe('WidgetConfigType')
        ->and($analysis->customImports)->toHaveKeys([
            '@js/types/settings',
            '@js/types/page-meta',
            '@js/types/widget-config',
        ]);
});

test('a $hidden filter key still gets a Pick<> reference by default, since hidden columns are published', function () {
    config()->set('ts-publish.models.exclude_hidden', false);

    $props = collect(
        new ResourceAstAnalyzer(new ReflectionClass(PostAttachmentFilterResource::class), Post::class)
            ->analyze()->properties,
    )->keyBy('name');

    expect($props['attachment_public']['type'])->toBe("Pick<Attachment, 'id' | 'filename'>")
        ->and($props['attachment_hidden']['type'])->toBe("Pick<Attachment, 'id' | 'internal_notes'>");
});

test('a $hidden filter key falls back to inline expansion instead of Pick<> when exclude_hidden is enabled', function () {
    // Pick<T, K> constrains K to keyof T, and exclude_hidden keeps a $hidden column out of the model interface.
    config()->set('ts-publish.models.exclude_hidden', true);

    $props = collect(
        new ResourceAstAnalyzer(new ReflectionClass(PostAttachmentFilterResource::class), Post::class)
            ->analyze()->properties,
    )->keyBy('name');

    expect($props['attachment_public']['type'])->toBe("Pick<Attachment, 'id' | 'filename'>")
        ->and($props['attachment_hidden']['type'])->toBe('{ id: number; internal_notes: string | null }');
});

test('drops hidden columns from an implicitly-derived resource property set', function () {
    // except() derives its property set from every model attribute minus the named keys, so
    // it is implicit — a $hidden column must not survive even though it was never named.
    config()->set('ts-publish.models.exclude_hidden', true);

    $props = collect(
        new ResourceAstAnalyzer(new ReflectionClass(UserExceptResource::class), User::class)
            ->analyze()->properties,
    )->keyBy('name');

    expect($props)->not->toHaveKey('password')
        ->and($props)->not->toHaveKey('remember_token')
        ->and($props)->toHaveKey('name');
});

test('keeps a hidden column the resource named explicitly', function () {
    // only() takes the property set verbatim from the named keys, so it is explicit — a
    // $hidden column the caller named must still come through.
    config()->set('ts-publish.models.exclude_hidden', true);

    $props = collect(
        new ResourceAstAnalyzer(new ReflectionClass(UserOnlyHiddenResource::class), User::class)
            ->analyze()->properties,
    )->keyBy('name');

    expect($props)->toHaveKey('password')
        ->and($props['password']['type'])->toBe('string');
});

test('a relation except() drops hidden columns from the derived key list', function () {
    // WarehouseResource::last_user_activity_by_mostly = $this->last_user_activity_by?->except(['id', 'name'])
    // is a multi-model accessor union (CrmUser|User); each arm now references its own model via
    // Pick<>, whose key set comes from publishedColumnNames() — so $hidden columns drop there too.
    config()->set('ts-publish.models.exclude_hidden', true);

    $props = collect(
        new ResourceAstAnalyzer(new ReflectionClass(WarehouseResource::class), Warehouse::class)
            ->analyze()->properties,
    )->keyBy('name');

    $type = $props['last_user_activity_by_mostly']['type'];

    expect($type)->not->toContain('password')
        ->and($type)->not->toContain('remember_token')
        ->and($type)->toContain("'email'");
});

test('a multi-model accessor union references each same-basename arm as its own Pick<>', function () {
    // Warehouse::lastUserActivityBy is Attribute<CrmUser|User|null, never>. At the analyzer level, before
    // alias rewriting, both arms render class_basename() as the bare 'User' — they must still stay two
    // distinct arms, keyed by FQCN, rather than the second being deduped away by its rendered string.
    $analysis = new ResourceAstAnalyzer(new ReflectionClass(WarehouseResource::class), Warehouse::class)
        ->analyze();

    $type = collect($analysis->properties)->keyBy('name')['last_user_activity_by_mostly']['type'];

    expect(substr_count($type, 'Pick<User, '))->toBe(2)
        ->and($analysis->inlineModelFqcns['last_user_activity_by_mostly'])->toBe([CrmUser::class, User::class]);
});

test('a multi-model accessor union of two unrelated models keeps both per-arm FQCNs', function () {
    // Warehouse::lastCheckedBy is Attribute<Image|User|null, never> — Image and User share no basename,
    // so this pins the FQCN list still carries both models even without a basename collision to expose it.
    $analysis = new ResourceAstAnalyzer(new ReflectionClass(WarehouseResource::class), Warehouse::class)
        ->analyze();

    $type = collect($analysis->properties)->keyBy('name')['last_checked_by_mostly']['type'];

    expect($type)->toContain('Pick<Image, ')
        ->and($type)->toContain('Pick<User, ')
        ->and($analysis->inlineModelFqcns['last_checked_by_mostly'])->toBe([Image::class, User::class]);
});

test('a declining arm never registers its own FQCN against an occurrence it did not produce', function () {
    // probe_mixed filters on 'phone', a column on App\Models\User but not Crm\Models\User: the CrmUser
    // arm declines Pick<> and falls back to { id: number } — no bare 'User' token — while the other arm
    // resolves to Pick<User, ...>. Only one FQCN belongs in the queue: one per real occurrence, not per arm.
    $analysis = new ResourceAstAnalyzer(new ReflectionClass(WarehouseResource::class), Warehouse::class)
        ->analyze();

    $type = collect($analysis->properties)->keyBy('name')['probe_mixed']['type'];

    expect($type)->toBe("{ id: number } | Pick<User, 'id' | 'phone'> | null")
        ->and($analysis->inlineModelFqcns['probe_mixed'])->toBe([User::class]);
});

test('relation except() expands to database columns only, matching Model::except() at runtime', function () {
    // HasAttributes::except() iterates getAttributes(): it never reads $this->relations, and
    // mergeAttributeFromAttributeCasts() refuses to merge a get-only Attribute back into $attributes.
    $analyzer = new class(new ReflectionClass(WarehouseResource::class), Warehouse::class) extends ResourceAstAnalyzer
    {
        /** @return array{type: string, enumFqcns: list<class-string>, modelFqcns: list<class-string>, customImports: array<string, list<string>>} */
        public function expose(): array
        {
            return $this->resolveFilteredRelationType(Image::class, ['created_at', 'updated_at'], false);
        }
    };

    // Every column create_images_table declares, in migration order, minus the two excluded keys.
    $expected = '{ id: number; imageable_type: string; imageable_id: number; url: string; '
        .'alt_text: string | null; disk: string; path: string; mime_type: string; size_bytes: number; '
        .'width: number | null; height: number | null; sort_order: number; metadata: unknown[] | null }';

    expect($analyzer->expose()['type'])->toBe($expected);
});

test('relation except() honours models.exclude_hidden on the columns it keeps', function (bool $excludeHidden, bool $passwordPresent) {
    config()->set('ts-publish.models.exclude_hidden', $excludeHidden);

    $analyzer = new class(new ReflectionClass(WarehouseResource::class), Warehouse::class) extends ResourceAstAnalyzer
    {
        /** @return array{type: string} */
        public function expose(): array
        {
            return $this->resolveFilteredRelationType(User::class, ['created_at', 'updated_at'], false);
        }
    };

    $type = $analyzer->expose()['type'];

    expect(str_contains($type, 'password:'))->toBe($passwordPresent)
        ->and(str_contains($type, 'remember_token:'))->toBe($passwordPresent)
        ->and($type)->toContain('email: string');
})->with([
    'hidden excluded' => [true, false],
    'hidden kept' => [false, true],
]);

test('relation only() still resolves a named accessor and a named relation, unlike except()', function () {
    // HasAttributes::only() calls getAttribute() per named key, so both do come back at runtime. The
    // columns-only change touched the $include === false branch only, so this cannot fail from it: it is a
    // standing guard that the include branch keeps resolving accessors and relations, not proof of that fix.
    $analyzer = new class(new ReflectionClass(WarehouseResource::class), Warehouse::class) extends ResourceAstAnalyzer
    {
        /** @return array{type: string, enumFqcns: list<class-string>, modelFqcns: list<class-string>, customImports: array<string, list<string>>} */
        public function expose(): array
        {
            return $this->resolveFilteredRelationType(User::class, ['name', 'initials', 'posts'], true);
        }
    };

    expect($analyzer->expose()['type'])->toBe('{ name: string; initials: string; posts: Post[] }');
});

test('an inline array member keeps its own per-occurrence FQCNs from a multi-FQCN accessor', function () {
    // probe_nested = ['first' => $this->last_user_activity_by, 'second' => $this->manager]. first is a
    // multi-FQCN accessor (CrmUser|User), so its two classFqcns plus second's single one must all
    // survive in occurrence order — not collapse through the array literal's self-keyed model map.
    $analysis = new ResourceAstAnalyzer(
        new ReflectionClass(WarehouseResource::class), Warehouse::class,
    )->analyze();

    expect($analysis->inlineModelFqcns)->toHaveKey('probe_nested')
        ->and($analysis->inlineModelFqcns['probe_nested'])->toBe([CrmUser::class, User::class, User::class]);
});

test('analyzeCoalesce() keeps the surviving operands FQCN channels', function () {
    $analysis = new ResourceAstAnalyzer(
        new ReflectionClass(CoalesceChannelResource::class), Order::class,
    )->analyze();
    $props = collect($analysis->properties)->keyBy('name');

    expect($props['buyer']['type'])->toBe('User | null')
        ->and($analysis->modelFqcns)->toContain(User::class)
        ->and($props['status']['type'])->toBe('OrderStatusType')
        ->and($analysis->directEnumFqcns)->toContain(OrderStatus::class);
});

test('a reflected $this->method() return dispatches its enum and model FQCNs', function () {
    // The raw [...$tsInfo] spread carried enumFqcns/classFqcns, which no dispatcher reads.
    $analysis = new ResourceAstAnalyzer(
        new ReflectionClass(ReflectedMethodChannelResource::class), Order::class,
    )->analyze();
    $props = collect($analysis->properties)->keyBy('name');

    expect($props['fallback_status']['type'])->toBe('StatusType')
        ->and($analysis->directEnumFqcns)->toContain(Status::class)
        ->and($props['fallback_owner']['type'])->toBe('User')
        ->and($analysis->modelFqcns)->toContain(User::class);
});

test('SomeClass::CONSTANT resolves the constant value without regressing Foo::class or enum cases', function () {
    $analysis = new ResourceAstAnalyzer(
        new ReflectionClass(ClassConstantResource::class), Order::class,
    )->analyze();
    $props = collect($analysis->properties)->keyBy('name');

    $nestedChannelsShape = '{ in_app: { status_updates: boolean; comments: boolean }; '
        .'digest: { status_updates: boolean; comments: boolean } }';

    expect($props['owner_minimum_channels']['type'])->toBe($nestedChannelsShape)
        ->and($props['max_retries']['type'])->toBe('number')
        ->and($props['schema_version']['type'])->toBe('number')
        // parent::CONSTANT, resolved from AbstractVersionedResource via getParentClass().
        ->and($props['base_version']['type'])->toBe('number')
        // A constant whose own initializer is another class's enum case.
        ->and($props['default_status']['type'])->toBe('StatusType')
        ->and($analysis->directEnumFqcns)->toContain(Status::class)
        // The left arm ($this->totally_unmapped_field) is unresolvable, so the constant on the
        // right — the same kind eaglesys's default_subscription_channels falls back to — wins.
        ->and($props['fallback_channels']['type'])->toBe($nestedChannelsShape)
        // A plain list where every element agrees resolves to an element array.
        ->and($props['channel_tags']['type'])->toBe('string[]')
        // A list whose elements don't agree resolves to a union element array.
        ->and($props['mixed_tags']['type'])->toBe('(string | number)[]')
        // A list nested inside a keyed constant resolves the same way a top-level one does,
        // rather than the Record<string, unknown> a keyless item would otherwise misreport.
        ->and($props['nested_tags']['type'])->toBe('{ primary: string[]; secondary: string[] }')
        // Negative case: an undefined-constant reference degrades to unknown at read time
        // instead of throwing and aborting the whole generation run.
        ->and($props['broken_channels']['type'])->toBe('unknown')
        // Negative case: one element past MAX_CONSTANT_ARRAY_ELEMENTS.
        ->and($props['over_element_limit']['type'])->toBe('unknown')
        // Negative case: one level past MAX_CONSTANT_ARRAY_DEPTH.
        ->and($props['over_depth_limit']['type'])->toBe('unknown')
        // An enum case nested inside a keyed constant — the FQCN must survive embedding.
        ->and($props['status_map']['type'])->toBe('{ status: OrderStatusType }')
        ->and($analysis->directEnumFqcns)->toContain(OrderStatus::class)
        // An enum case nested inside a list constant — same requirement, list shape.
        ->and($props['status_list']['type'])->toBe('OrderStatusType[]')
        // All-int, non-sequential keys: every member is dropped, not a regression.
        ->and($props['all_int_keys']['type'])->toBe('Record<string, unknown>')
        // A mixed string/int-keyed constant: the int-keyed member is silently dropped.
        ->and($props['mixed_keys']['type'])->toBe('{ a: number }')
        // New behaviour: `Foo::class` now types as a plain string instead of unknown. The four
        // risky call sites never reach this branch — they resolve their ClassConstFetch argument
        // directly, without going through analyzeValueExpression() — so this is new behaviour, not a guard.
        ->and($props['resource_marker']['type'])->toBe('string')
        // Unaffected: an enum case reached through EnumResource::make() — a separate,
        // pre-existing path (resolveEnumFromPropertyArg()) this feature does not touch.
        ->and($props['status_marker']['type'])->toContain('Status')
        ->and($analysis->enumResources)->toHaveKey('status_marker', Status::class);
});

describe('ResourceAstAnalyzer with MerchantResource (toResource()/toResourceCollection())', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(MerchantResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Merchant::class))->analyze();
        $this->props = collect($this->analysis->properties)->keyBy('name');
        $this->nested = $this->analysis->nestedResources;
    });

    test('whenLoaded closure toResource() resolves the related model by naming convention', function () {
        expect($this->props['owner_via_closure']['type'])->toBe('UserResource')
            ->and($this->props['owner_via_closure']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('owner_via_closure', UserResource::class);
    });

    test('whenLoaded closure toResource(SomeResource::class) honours the explicit argument', function () {
        expect($this->props['owner_explicit']['type'])->toBe('UserResource')
            ->and($this->props['owner_explicit']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('owner_explicit', UserResource::class);
    });

    test('toResource(SomeResource::SOME_CONSTANT) — a non-::class constant — degrades to unknown, not a wrong resource', function () {
        expect($this->props['owner_variant_constant']['type'])->toBe('unknown')
            ->and($this->nested)->not->toHaveKey('owner_variant_constant');
    });

    test('$this->relation->toResource() resolves directly, without a whenLoaded wrapper', function () {
        expect($this->props['owner_direct']['type'])->toBe('UserResource')
            ->and($this->props['owner_direct']['optional'])->toBeFalse()
            ->and($this->nested)->toHaveKey('owner_direct', UserResource::class);
    });

    test('whenLoaded closure toResourceCollection() resolves the element model by convention', function () {
        expect($this->props['staff_via_closure']['type'])->toBe('UserResource[]')
            ->and($this->props['staff_via_closure']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('staff_via_closure', UserResource::class);
    });

    test('whenLoaded closure toResourceCollection(SomeResource::class) honours the explicit argument', function () {
        expect($this->props['staff_explicit']['type'])->toBe('UserResource[]')
            ->and($this->props['staff_explicit']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('staff_explicit', UserResource::class);
    });

    test('toResource() prefers the #[UseResource] attribute over the naming convention', function () {
        // Non-vacuous: Workbench\App\Http\Resources\TrackingEventResource is the naming-convention
        // candidate for the related model and genuinely exists — an inverted order would resolve
        // to it instead of EventLogResource, so this assertion can actually discriminate.
        expect(class_exists(AppTrackingEventResource::class))->toBeTrue();

        expect($this->props['history_event']['type'])->toBe('EventLogResource')
            ->and($this->props['history_event']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('history_event', EventLogResource::class);
    })->skip(
        ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResource'),
        'UseResource attribute requires Laravel 12.29+',
    );

    test('toResource() degrades to unknown when the related model has no matching resource class', function () {
        // Guards the fixture itself: if either candidate ever gets created, this test would need
        // a different negative case rather than silently passing for the wrong reason.
        expect(class_exists('Workbench\App\Http\Resources\ActivityResource'))->toBeFalse()
            ->and(class_exists('Workbench\App\Http\Resources\Activity'))->toBeFalse();

        expect($this->props['filing']['type'])->toBe('unknown')
            ->and($this->nested)->not->toHaveKey('filing');
    });

    test('toResource() degrades to unknown when the related model is not under a \Models\ namespace', function () {
        // Guards the fixture itself: DatabaseNotification must stay outside \Models\ for this to
        // exercise guessResourceName()'s bail-to-[] branch rather than the no-candidate-exists one.
        expect(str_contains(DatabaseNotification::class, '\Models\\'))->toBeFalse();

        expect($this->props['alert']['type'])->toBe('unknown')
            ->and($this->nested)->not->toHaveKey('alert');
    });

    test('toResource() prefers the Resource-suffixed naming candidate over the bare one', function () {
        // Non-vacuous: Workbench\App\Http\Resources\Registrar (bare) also exists — an inverted
        // candidate order would resolve to it instead of RegistrarResource.
        expect(class_exists(BareRegistrarResource::class))->toBeTrue();

        expect($this->props['registrar']['type'])->toBe('RegistrarResource')
            ->and($this->props['registrar']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('registrar', RegistrarResource::class);
    });

    test('toResourceCollection() prefers the guessed {X}Collection class over the bare resource', function () {
        // Non-vacuous: SupplierResource (the bare fallback) also exists and collects a DIFFERENT
        // element than SupplierCollection does, so the two possible orderings are distinguishable.
        expect(class_exists(SupplierResource::class))->toBeTrue();

        expect($this->props['suppliers']['type'])->toBe('SupplierSummaryResource[]')
            ->and($this->props['suppliers']['optional'])->toBeTrue()
            ->and($this->nested)->toHaveKey('suppliers', SupplierSummaryResource::class);
    });

    test('toResourceCollection() degrades to unknown when #[UseResourceCollection]\'s element is undeterminable, without falling through', function () {
        // RegistrarResource exists and would be the WRONG fallback element type if the matched
        // #[UseResourceCollection] attribute fell through instead of stopping hard.
        expect(class_exists(RegistrarResource::class))->toBeTrue();

        expect($this->props['registrars']['type'])->toBe('unknown')
            ->and($this->nested)->not->toHaveKey('registrars');
    })->skip(
        ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResourceCollection'),
        'UseResourceCollection attribute requires Laravel 12.29+',
    );

    test('whenLoaded closure ->map->only() resolves the HigherOrderCollectionProxy per element', function () {
        // arrayWrapType() parenthesizes on any '|', including one nested inside the braces.
        expect($this->props['staff_map_only']['type'])
            ->toBe('({ id: number; name: string; role: RoleType | null; last_login_at: string | null })[]')
            ->and($this->props['staff_map_only']['optional'])->toBeTrue()
            ->and($this->analysis->inlineEnumFqcns)->toHaveKey('staff_map_only')
            ->and($this->analysis->inlineEnumFqcns['staff_map_only'])->toContain(Role::class);
    });

    test('whenLoaded closure ->map->except() resolves the complement per element', function () {
        expect($this->props['registrars_map_except']['type'])->toBe('{ name: string }[]')
            ->and($this->props['registrars_map_except']['optional'])->toBeTrue();
    });

    test('->map->only() on a singular relation param stays unknown, not the element shape', function () {
        // historyEvent is a BelongsTo: $m binds to varModelBindings, not varCollectionBindings,
        // so the ->map proxy must not match — a guess here would silently mistype a real property.
        expect($this->props['history_event_map_only']['type'])->toBe('unknown');
    });

    test('an empty registry fails open, so a convention guess outside the published set still resolves', function () {
        // RunnerForSource never calls collect(), so single-file watch-mode regeneration analyzes
        // with an empty registry. Failing closed there would strip every nested resource.
        expect(PublishedResourceRegistry::isEmpty())->toBeTrue();

        expect($this->props['unpublished_guess']['type'])->toBe('AttachmentResource')
            ->and($this->nested)->toHaveKey('unpublished_guess', AttachmentResource::class)
            ->and($this->props['unpublished_guess_collection']['type'])->toBe('AttachmentResource[]')
            ->and($this->nested)->toHaveKey('unpublished_guess_collection', AttachmentResource::class);
    });

    test('a convention-guessed resource outside the published set is not resolved', function () {
        // Non-vacuous: every losing candidate genuinely exists — only #[TsExclude] keeps the two
        // Attachment classes out of the published set, so class_exists() alone would accept them.
        expect(class_exists(AttachmentResource::class))->toBeTrue()
            ->and(class_exists(AttachmentCollection::class))->toBeTrue()
            ->and(class_exists(UserResource::class))->toBeTrue();

        PublishedResourceRegistry::register([TeamResource::class]);

        $reflection = new ReflectionClass(MerchantResource::class);
        $analysis = (new ResourceAstAnalyzer($reflection, Merchant::class))->analyze();
        $props = collect($analysis->properties)->keyBy('name');

        expect($props['unpublished_guess']['type'])->toBe('unknown')
            ->and($props['unpublished_guess_collection']['type'])->toBe('unknown')
            ->and($analysis->nestedResources)->not->toHaveKey('unpublished_guess')
            ->and($analysis->nestedResources)->not->toHaveKey('unpublished_guess_collection')
            // Every convention branch is gated, not just the Attachment fixture's.
            ->and($props['owner_via_closure']['type'])->toBe('unknown')
            // An explicitly named resource is the developer's declaration and stays ungated.
            ->and($props['owner_explicit']['type'])->toBe('UserResource')
            ->and($analysis->nestedResources)->toHaveKey('owner_explicit', UserResource::class);

        PublishedResourceRegistry::reset();
    });
});

describe('collectedResourceClass() naming-convention branch is gated on the published set', function () {
    test('an empty registry fails open, so a convention guess outside the published set still resolves', function () {
        expect(PublishedResourceRegistry::isEmpty())->toBeTrue();

        $reflection = new ReflectionClass(LedgerCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();
        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('LedgerResource[]')
            ->and($analysis->nestedResources)->toHaveKey('data', LedgerResource::class);
    });

    test('the naming convention resolves its first, Resource-suffixed candidate when published', function () {
        PublishedResourceRegistry::register([SupplierSummaryResource::class]);

        $reflection = new ReflectionClass(SupplierSummaryCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();
        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('SupplierSummaryResource[]')
            ->and($analysis->nestedResources)->toHaveKey('data', SupplierSummaryResource::class);

        PublishedResourceRegistry::reset();
    });

    test('the naming convention falls through to its bare, unsuffixed candidate when published', function () {
        // Non-vacuous: Admin\StoreResource does not exist, so only the bare candidate can resolve.
        expect(class_exists('Workbench\App\Http\Resources\Admin\StoreResource'))->toBeFalse();

        PublishedResourceRegistry::register([AdminStore::class]);

        $reflection = new ReflectionClass(AdminStoreCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();
        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('Store[]')
            ->and($analysis->nestedResources)->toHaveKey('data', AdminStore::class);

        PublishedResourceRegistry::reset();
    });

    test('both naming-convention candidates are rejected when neither is published', function () {
        // Non-vacuous: both losing candidates genuinely exist — only #[TsExclude] keeps them
        // out of the published set, so class_exists() alone would accept either.
        expect(class_exists(LedgerResource::class))->toBeTrue()
            ->and(class_exists(Ledger::class))->toBeTrue();

        PublishedResourceRegistry::register([UserResource::class]);

        $reflection = new ReflectionClass(LedgerCollection::class);
        $analysis = (new ResourceAstAnalyzer($reflection))->analyze();
        $data = collect($analysis->properties)->firstWhere('name', 'data');

        expect($data)->not->toBeNull()
            ->and($data['type'])->toBe('unknown')
            ->and($analysis->nestedResources)->not->toHaveKey('data');

        PublishedResourceRegistry::reset();
    });
});

describe('ResourceAstAnalyzer with NestedResourceSpreadResource (spread-of-a-resource inside a nested array)', function () {
    beforeEach(function () {
        $this->analysis = (new ResourceAstAnalyzer(new ReflectionClass(NestedResourceSpreadResource::class), Team::class))
            ->analyze();
        $this->props = collect($this->analysis->properties)->keyBy('name');
    });

    test('spread of a resolved resource plus a sibling key intersects, minus the overridden key, inside a mapped multi-statement closure', function () {
        // 'profile' is a real UserResource key too (Profile | null) — PHP's spread lets the
        // explicit 'profile' win, so the resource arm must Omit<> it, not intersect it.
        expect($this->props['members_with_profile']['type'])
            ->toBe("(Omit<UserResource, 'profile'> & { profile: ProfileResource })[]")
            ->and($this->props['members_with_profile']['optional'])->toBeTrue()
            ->and(array_values($this->analysis->nestedResources))->toContain(UserResource::class)
            ->and(array_values($this->analysis->nestedResources))->toContain(ProfileResource::class);
    });

    test('spread alone with no sibling keys collapses to just the resource type', function () {
        expect($this->props['members_bare']['type'])->toBe('UserResource[]')
            ->and($this->props['members_bare']['optional'])->toBeTrue();
    });

    test('a spread of a bound model\'s toArray() intersects the model with the literal\'s own keys', function () {
        // 'flag' is not a User column, but the arm is Omit<>'d anyway — the same unconditional
        // subtraction members_double_spread pins for 'note' against UserResource.
        expect($this->props['members_model_spread']['type'])->toBe("(Omit<User, 'flag'> & { flag: boolean })[]")
            ->and($this->props['members_model_spread']['optional'])->toBeTrue()
            ->and(array_values($this->analysis->modelFqcns))->toContain(User::class);
    });

    test('a to-many whenLoaded param\'s own toArray() spread is a Record<number, Model>, not the element model', function () {
        // $members is bound in varCollectionBindings (a list), not varModelBindings (one model) —
        // spreadModelToArrayFqcn() must not fall back to closureRelationModelClass for it. The
        // spread renumbers the members 0..n, so they key by index beside the literal's 'flag'.
        expect($this->props['members_collection_spread']['type'])->toBe('Record<number, User> & { flag: boolean }')
            ->and($this->props['members_collection_spread']['optional'])->toBeTrue()
            ->and($this->props['members_collection_spread']['type'])->not->toContain('Omit<')
            ->and($this->analysis->inlineModelFqcns['members_collection_spread'] ?? [])->toBe([User::class]);
    });

    test('two resource spreads plus a sibling key intersect in order, each Omit<>\'d against what later overrides it', function () {
        // 'note' is explicit and wins over both arms; ProfileResource (spread 2nd) wins its own
        // keys over UserResource (spread 1st) — so UserResource's arm excludes both.
        expect($this->props['members_double_spread']['type'])
            ->toBe("(Omit<UserResource, 'note' | keyof ProfileResource> & Omit<ProfileResource, 'note'> & { note: string })[]")
            ->and($this->props['members_double_spread']['optional'])->toBeTrue();
    });

    test('an untyped map() closure param falls back to the receiver\'s own relation binding', function () {
        expect($this->props['members_with_profile_untyped']['type'])
            ->toBe("(Omit<UserResource, 'profile'> & { profile: ProfileResource })[]")
            ->and($this->props['members_with_profile_untyped']['optional'])->toBeTrue();
    });

    test('an untyped map() closure param on a receiver bound to a singular relation stays unknown', function () {
        expect($this->props['owner_map_untyped']['type'])->toBe('unknown');
    });

    test('two spread arms colliding on real (non-id) keys: the earlier arm Omits the later arm\'s keyof, by construction', function () {
        // No explicit keys here — isolates the between-arms subtraction from the explicit-key one.
        expect($this->props['members_colliding_spread']['type'])
            ->toBe('(Omit<UserResource, keyof TeamMemberResource> & TeamMemberResource)[]')
            ->and($this->props['members_colliding_spread']['optional'])->toBeTrue();
    });

    test('a model arm, a resource arm, then a model arm again stays in source order across kinds', function () {
        // Mirror pair with members_resource_then_model_spread below. Both fail if arm collection
        // is ever grouped by kind instead of source order — a single-kind fixture can't show that.
        expect($this->props['members_model_then_resource_spread']['type'])->toBe(
            "(Omit<User, 'flag' | keyof UserResource | keyof User> & ".
            "Omit<UserResource, 'flag' | keyof User> & ".
            "Omit<User, 'flag'> & { flag: boolean })[]"
        )->and($this->props['members_model_then_resource_spread']['optional'])->toBeTrue();
    });

    test('the mirror order — resource, model, resource — also stays in source order across kinds', function () {
        expect($this->props['members_resource_then_model_spread']['type'])->toBe(
            "(Omit<UserResource, 'flag' | keyof User | keyof UserResource> & ".
            "Omit<User, 'flag' | keyof UserResource> & ".
            "Omit<UserResource, 'flag'> & { flag: boolean })[]"
        )->and($this->props['members_resource_then_model_spread']['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with BranchedInlineFqcnResource (branch union nullability)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(BranchedInlineFqcnResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Warehouse::class))->analyze();
    });

    // Both branches are `$this->regional_hub?->only([...])`, so each arm arrives already nullable.
    test('two nullable branch arms union under one trailing null instead of one null per arm', function () {
        $type = collect($this->analysis->properties)->firstWhere('name', 'regional_hub_contacts')['type'];

        expect($type)->toBe(
            '{ primaryContact: User | null; manager: User | null }'
            .' | { manager: User | null; secondaryContact: User | null; primaryContact: User | null } | null'
        )->and($type)->not->toContain('} | null | {');
    });
});

describe('ResourceAstAnalyzer with an arbitrary method name', function () {
    test('analyzes a non-toArray array-returning method by name', function () {
        $reflection = new ReflectionClass(TagResource::class);
        $analysis = (new ResourceAstAnalyzer($reflection, Tag::class, 'summaryPayload'))->analyze();

        $names = array_column($analysis->properties, 'name');
        $label = collect($analysis->properties)->firstWhere('name', 'label');

        expect($names)->toContain('id', 'label')
            ->and($label['type'])->toBe('string')
            ->and($label['optional'])->toBeFalse();
    });

    test('a missing generic method yields an empty analysis instead of a model dump', function () {
        $reflection = new ReflectionClass(TagResource::class);
        $analysis = (new ResourceAstAnalyzer($reflection, Tag::class, 'noSuchMethod'))->analyze();

        expect($analysis->properties)->toBeEmpty();
    });

    test('an explicit "toArray" method name still falls back to the model dump', function () {
        $reflection = new ReflectionClass(EmptyWithMixinResource::class);
        $analysis = (new ResourceAstAnalyzer($reflection, User::class, 'toArray'))->analyze();

        $names = array_column($analysis->properties, 'name');

        expect($analysis->properties)->not->toBeEmpty()
            ->and($names)->toContain('id', 'name', 'email');
    });
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use Workbench\Accounting\Http\Resources\InvoiceResource;
use Workbench\Accounting\Models\Invoice;
use Workbench\App\Enums\OrderStatus;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\BareFuncCallResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\ClosureControlFlowResource;
use Workbench\App\Http\Resources\ClosureUnionMetadataResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\CommonResource;
use Workbench\App\Http\Resources\ConditionalParamArrayResource;
use Workbench\App\Http\Resources\ConditionalParamEnumResource;
use Workbench\App\Http\Resources\ConditionalParamFullClosureResource;
use Workbench\App\Http\Resources\ConditionalParamMappedResource;
use Workbench\App\Http\Resources\ConditionalParamPrimitiveResource;
use Workbench\App\Http\Resources\ControlFlowReturnResource;
use Workbench\App\Http\Resources\DelegatingResource;
use Workbench\App\Http\Resources\DelegatingWithMixinResource;
use Workbench\App\Http\Resources\EmptyResource;
use Workbench\App\Http\Resources\EmptyWithMixinResource;
use Workbench\App\Http\Resources\EnumNullFirstResource;
use Workbench\App\Http\Resources\ExtendedAddressResource;
use Workbench\App\Http\Resources\GuardClauseClosureResource;
use Workbench\App\Http\Resources\InlineArrayFqcnResource;
use Workbench\App\Http\Resources\LoopReturnResource;
use Workbench\App\Http\Resources\MediaTypeInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypePositiveInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypeResource;
use Workbench\App\Http\Resources\MediaTypeUnknownResource;
use Workbench\App\Http\Resources\MergeClosureResource;
use Workbench\App\Http\Resources\MergeMultiBranchClosureResource;
use Workbench\App\Http\Resources\MiscCollection;
use Workbench\App\Http\Resources\ModelWrappedPropResource;
use Workbench\App\Http\Resources\NonArrayReturnResource;
use Workbench\App\Http\Resources\OrderClosureResource;
use Workbench\App\Http\Resources\OrderCollection;
use Workbench\App\Http\Resources\OrderDetailResource;
use Workbench\App\Http\Resources\OrderExceptResource;
use Workbench\App\Http\Resources\OrderFilterEdgeResource;
use Workbench\App\Http\Resources\OrderItemResource;
use Workbench\App\Http\Resources\OrderOnlyResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\OrderSummaryResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\QuirkyResource;
use Workbench\App\Http\Resources\SpreadJsonBaseResource;
use Workbench\App\Http\Resources\SpreadWithClosureResource;
use Workbench\App\Http\Resources\SpreadWithGuardClauseClosureResource;
use Workbench\App\Http\Resources\SpreadWithGuardDoubleClosureReturnResource;
use Workbench\App\Http\Resources\TagResource;
use Workbench\App\Http\Resources\TeamMemberResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Http\Resources\TernaryResource;
use Workbench\App\Http\Resources\ToArrayCastsResource;
use Workbench\App\Http\Resources\TraitSpreadCoverageResource;
use Workbench\App\Http\Resources\UnitEnumResource;
use Workbench\App\Http\Resources\UserCollection;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\VarReturnSpreadResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Tag;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Blog\Enums\ArticleStatus;
use Workbench\Blog\Enums\ContentType;
use Workbench\Blog\Http\Resources\ApiArticleResource;
use Workbench\Blog\Http\Resources\ReactionResource;
use Workbench\Blog\Models\Article;
use Workbench\Blog\Models\Reaction;
use Workbench\Crm\Http\Resources\DealResource;
use Workbench\Crm\Models\Deal;
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

    test('(array) cast resolves to unknown[]', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'heading_content');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('unknown[]')
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

describe('ResourceAstAnalyzer with InvoiceResource', function () {
    test('resolves when wrapping EnumResource::make as optional', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $status = collect($analysis->properties)->firstWhere('name', 'status');

        expect($status['optional'])->toBeTrue();
    });

    test('latest_payment_only resolves inline type from accessor-returned model', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'latest_payment_only');

        expect($prop['type'])
            ->not->toBe('unknown')
            ->toContain('invoice_id: number')
            ->toContain('| null');
    });

    test('latest_payment_excluded resolves inline type from accessor-returned model', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $prop = collect($analysis->properties)->firstWhere('name', 'latest_payment_excluded');

        expect($prop['type'])
            ->not->toBe('unknown')
            ->toContain('id: number')
            ->toContain('| null');
    });

    test('has enum imports from accessor model filter (latest_payment_only)', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        expect($analysis->directEnumFqcns)
            ->toHaveKey('Workbench\Accounting\Enums\PaymentStatus')
            ->toHaveKey('Workbench\App\Enums\PaymentMethod')
            ->toHaveKey('Workbench\App\Enums\Currency');
    });

    test('has model imports from accessor model filter (latest_payment_excluded)', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)
            ->toHaveKey('Workbench\Accounting\Models\Invoice');
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

    test('test order_limited only has id, total or null', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        $orderLimited = collect($analysis->properties)->firstWhere('name', 'order_limited');

        expect($orderLimited['type'])->toBe('{ id: number; total: number } | null');
    });

    test('test order_extended does not have created_at & updated_at', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        $orderExtended = collect($analysis->properties)->firstWhere('name', 'order_extended');

        expect($orderExtended['type'])
            ->not->toContain('created_at')
            ->not->toContain('updated_at');
    });

    test('has enum imports from inline relation filter (order_extended)', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        expect($analysis->directEnumFqcns)
            ->toHaveKey('Workbench\App\Enums\OrderStatus')
            ->toHaveKey('Workbench\App\Enums\PaymentMethod')
            ->toHaveKey('Workbench\App\Enums\Currency');
    });

    test('has model imports from inline relation filter (order_extended)', function () {
        $reflection = new ReflectionClass(OrderItemResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, OrderItem::class);
        $analysis = $analyzer->analyze();

        expect($analysis->modelFqcns)
            ->toHaveKey('Workbench\App\Models\User')
            ->toHaveKey('Workbench\App\Models\OrderItem');
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
        config()->set('ts-publish.nullable_relations', false);

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

        // Parent PostResource has: id, title, content, status, visibility, priority
        // ApiPostResource adds: status, visibility, priority (overrides parent's EnumResource versions)
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

        // 'id' from parent should appear before the child's 'status'
        expect($idIndex)->toBeLessThan($statusIndex);
    });

    test('child overrides clear parent enum resource tracking for overridden keys', function () {
        $reflection = new ReflectionClass(ApiPostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        // Parent PostResource uses EnumResource::make() for status, visibility, priority
        // Child overrides those keys with plain $this->prop, clearing the parent's enum resource tracking
        // But _new variants from parent are NOT overridden, so they remain as enum resources
        expect($analysis->enumResources)
            ->not->toHaveKey('status')
            ->not->toHaveKey('visibility')
            ->not->toHaveKey('priority')
            ->toHaveKey('status_new')
            ->toHaveKey('visibility_new')
            ->toHaveKey('priority_new');
    });

    test('inherits customImports from parent trait TsResourceCasts', function () {
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

describe('ResourceAstAnalyzer non-array return', function () {
    test('returns empty analysis for non-array non-parent return', function () {
        $reflection = new ReflectionClass(NonArrayReturnResource::class);
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

    test('applies TsResourceCasts type overrides on trait methods', function () {
        $location = collect($this->analysis->properties)->firstWhere('name', 'location');

        expect($location['type'])->toBe('GeoPoint');
    });

    test('applies TsResourceCasts optional flag on trait methods', function () {
        $flag = collect($this->analysis->properties)->firstWhere('name', 'flag');

        expect($flag['type'])->toBe('string | null')
            ->and($flag['optional'])->toBeTrue();
    });

    test('adds new properties from TsResourceCasts on trait methods', function () {
        $extra = collect($this->analysis->properties)->firstWhere('name', 'extra');

        expect($extra)->not->toBeNull()
            ->and($extra['type'])->toBe('Record<string, unknown>')
            ->and($extra['optional'])->toBeFalse();
    });

    test('populates customImports from TsResourceCasts import paths', function () {
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

describe('ResourceAstAnalyzer with OrderOnlyResource (spread only)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderOnlyResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('only spread includes exactly the listed properties', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->toContain('id', 'total', 'status')
            ->and($names)->toContain('user')
            ->and($names)->not->toContain('ulid', 'subtotal', 'tax', 'notes');
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
});

describe('ResourceAstAnalyzer with OrderExceptResource (direct return)', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(OrderExceptResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    test('except excludes the listed properties', function () {
        $names = array_column($this->analysis->properties, 'name');

        expect($names)->not->toContain('ip_address', 'user_agent');
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
        // All three spreads return null, so the analysis is empty
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

describe('ResourceAstAnalyzer with UserCollection (convention-based)', function () {
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

    test('resolves other properties as unknown when no model backing', function () {
        $totalCount = collect($this->analysis->properties)->firstWhere('name', 'total_count');

        expect($totalCount)->not->toBeNull()
            ->and($totalCount['type'])->toBe('unknown');
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

    test('resolves TsResourceCasts from bare function call spreads', function () {
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

    test('returns empty analysis for method call return (not array or variable)', function () {
        $reflection = new ReflectionClass(VarReturnSpreadResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, User::class);
        $analysis = $analyzer->analyze();

        // includeFromMethodCall returns $this->includeNonAnalyzable() — not an array literal
        // or variable, so the else fallback produces no properties for that spread
        $names = array_column($analysis->properties, 'name');

        expect($names)->not->toContain('dynamic');
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

        // Should appear exactly once (de-duplicated)
        expect($statusProps)->toHaveCount(1);

        // Unconditional assignment exists, so optional must be false
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

        // Parent CommonResource trait has 'id' from includeTypedExtras (type: string via PHPDoc)
        // Child $this->only(['id', ...]) resolves 'id' against Article model (type: number)
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

        // maxSizeMb(): int → number, icon(): string → string (verified via reflection)
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

    test('empty inline array resolves to Record<string, unknown>', function () {
        $reflection = new ReflectionClass(MediaTypePositiveInstanceOfResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection);
        $analysis = $analyzer->analyze();

        $empty = collect($analysis->properties)->firstWhere('name', 'empty');

        expect($empty['type'])->toBe('Record<string, unknown>');
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

        // The closure's inner properties should NOT appear as top-level properties
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

        // The closure's inner properties should NOT be top-level
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
        // $user->role is cast to Role::class — the FQCN should appear in directEnumFqcns
        // even though it's inside the nested inline object for the customer property
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

        // Plain $this->property and literal values resolve to 'unknown' at the AST level;
        // the transformer resolves actual types using model metadata downstream
        expect($props->firstWhere('name', 'id')['type'])->toBe('number')
            ->and($props->firstWhere('name', 'archived')['type'])->toBe('boolean')
            ->and($props->firstWhere('name', 'draft')['type'])->toBe('boolean')
            ->and($props->firstWhere('name', 'total')['type'])->toBe('number')
            ->and($props->firstWhere('name', 'status')['type'])->toBe('OrderStatusType');
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
            ->and($props->firstWhere('name', 'first_item_name')['type'])->toBe('unknown')
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
        // `fn (): ?string => $this->resource->user->email` — 3-deep non-nullsafe chain;
        // body now resolved by analyzePropertyChain, winning over the ?string annotation.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_email');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });

    test('annotation fallback fires when body is a FuncCall — user_email_annotated is string|null', function () {
        // `fn (): ?string => json_decode(...)` — json_decode() returns mixed (resolves to unknown) so the body stays unknown;
        // ?string annotation kicks in → string|null.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'user_email_annotated');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string | null')
            ->and($prop['optional'])->toBeTrue();
    });

    test('no annotation and unresolvable body — unresolvable_status is unknown', function () {
        // `fn () => json_decode(...)` — json_decode() returns mixed (resolves to unknown) so the body stays unknown;
        // no return type annotation → unknown.
        $prop = collect($this->analysis->properties)->firstWhere('name', 'unresolvable_status');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('unknown')
            ->and($prop['optional'])->toBeTrue();
    });

    test('enum annotation fallback resolves type and FQCN — resolvable_status is StatusType', function () {
        // `fn (): Status => json_decode(...)` — body is unresolvable (json_decode returns mixed → unknown);
        // Status annotation resolves to StatusType with directEnumFqcn tracking.
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
        // `fn () => $this->post->title` — proxy step $this->post skipped via closureRelationModelClass;
        // plain -> traversal resolves title on Post
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

describe('ResourceAstAnalyzer with ToArrayCastsResource — #[TsResourceCasts] on toArray() method', function () {
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

    test('registers custom import from method-level TsResourceCasts', function () {
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

    // Bug D: $this->when($this->status, fn ($status) => EnumResource::make($status))
    // $status is bound to $this->status (OrderStatus enum); EnumResource wraps it.
    test('when() param → EnumResource::make($status) resolves to OrderStatusType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_resource');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('OrderStatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    // Bug D: $this->when($this->status, fn ($status) => $status)
    // $status is the OrderStatus enum passed bare.
    test('when() param → bare $status return resolves to OrderStatusType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_bare');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('OrderStatusType')
            ->and($prop['optional'])->toBeTrue();
    });

    // Bug D: $this->when($this->currency, fn ($currency) => EnumResource::make($currency))
    // $currency is the Currency enum on Order.
    test('when() param → EnumResource::make($currency) resolves to CurrencyType not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'currency_resource');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('CurrencyType')
            ->and($prop['optional'])->toBeTrue();
    });

    // Bug A: $this->whenLoaded('user', fn ($user) => EnumResource::make($user->role))
    // $user is the User relation; $user->role is the Role enum.
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

    // Bug B: $this->whenLoaded('items', fn ($items) => $items->map(fn (OrderItem $item) => [...]))
    // Outer param $items → collection; inner typed param $item → array shape.
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

    // Bug C: fn () => $this->notes ?? 'none'
    // $this->notes is string|null; ?? 'none' strips the null → should be string.
    test('when() with coalesce (??) resolves to string not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_or_default');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('string')
            ->and($prop['optional'])->toBeTrue();
    });
});

describe('ResourceAstAnalyzer with ConditionalParamFullClosureResource — issue #38 full closure params', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamFullClosureResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // Bug B (full closure variant): function ($items) { return $items->map(function (OrderItem $item) { ... }); }
    test('full closure param → items->map() with typed inner closure resolves to array shape not unknown', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'items_mapped');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('{ id: number; name: string; quantity: number }[]')
            ->and($prop['optional'])->toBeTrue();
    });

    // Full closure: function ($status) { return EnumResource::make($status); }
    // (condition is $this->status !== null — a boolean expression, so $status param = true)
    test('full closure param → EnumResource::make resolves to non-unknown type', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'status_resource');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('OrderStatusType')
            ->and($prop['optional'])->toBeTrue();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// whenNotNull closure param binding — ConditionalParamPrimitiveResource
// ─────────────────────────────────────────────────────────────────────────────

describe('ResourceAstAnalyzer with ConditionalParamPrimitiveResource — whenNotNull param binding', function () {
    beforeEach(function () {
        $reflection = new ReflectionClass(ConditionalParamPrimitiveResource::class);
        $this->analysis = (new ResourceAstAnalyzer($reflection, Order::class))->analyze();
    });

    // whenNotNull($this->notes, fn ($notes) => strlen($notes)) → number (not string | null)
    test('whenNotNull() arrow fn param → strlen() resolves to number not string|null', function () {
        $prop = collect($this->analysis->properties)->firstWhere('name', 'notes_length');

        expect($prop)->not->toBeNull()
            ->and($prop['type'])->toBe('number')
            ->and($prop['optional'])->toBeTrue();
    });
});

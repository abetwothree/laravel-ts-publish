<?php

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use Workbench\Accounting\Http\Resources\InvoiceResource;
use Workbench\Accounting\Models\Invoice;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\DelegatingResource;
use Workbench\App\Http\Resources\DelegatingWithMixinResource;
use Workbench\App\Http\Resources\EmptyResource;
use Workbench\App\Http\Resources\EmptyWithMixinResource;
use Workbench\App\Http\Resources\ExtendedAddressResource;
use Workbench\App\Http\Resources\NonArrayReturnResource;
use Workbench\App\Http\Resources\OrderDetailResource;
use Workbench\App\Http\Resources\OrderItemResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\OrderSummaryResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\QuirkyResource;
use Workbench\App\Http\Resources\SpreadJsonBaseResource;
use Workbench\App\Http\Resources\TeamMemberResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Http\Resources\TraitSpreadCoverageResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Category;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\OrderItem;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Blog\Http\Resources\ReactionResource;
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

        expect($names)->toContain('id', 'title', 'content', 'status', 'visibility', 'priority');
    });

    test('identifies EnumResource::make calls', function () {
        $reflection = new ReflectionClass(PostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        expect($analysis->enumResources)
            ->toHaveKey('status')
            ->toHaveKey('visibility')
            ->toHaveKey('priority');
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
});

describe('ResourceAstAnalyzer with InvoiceResource', function () {
    test('resolves when wrapping EnumResource::make as optional', function () {
        $reflection = new ReflectionClass(InvoiceResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Invoice::class);
        $analysis = $analyzer->analyze();

        $status = collect($analysis->properties)->firstWhere('name', 'status');

        expect($status['optional'])->toBeTrue();
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

    test('handles non-mergeWhen key-less method calls gracefully', function () {
        expect($this->analysis->properties)->toBeArray();
    });

    test('handles mergeWhen with single arg gracefully', function () {
        // $this->mergeWhen(true) with 1 arg — mergeWhen requires 2 args
        expect($this->analysis->properties)->toBeArray();
    });

    test('handles mergeWhen with non-array second arg', function () {
        // $this->mergeWhen(cond, fn() => [...]) — closure, not array literal
        expect($this->analysis->properties)->toBeArray();
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

    test('child overrides clear parent enum resource tracking', function () {
        $reflection = new ReflectionClass(ApiPostResource::class);
        $analyzer = new ResourceAstAnalyzer($reflection, Post::class);
        $analysis = $analyzer->analyze();

        // Parent PostResource uses EnumResource::make() for status, visibility, priority
        // Child overrides those keys with plain $this->prop, clearing the parent's enum resource tracking
        expect($analysis->enumResources)->toBeEmpty();
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

    test('includes properties from trait methods without docblocks', function () {
        $plain = collect($this->analysis->properties)->firstWhere('name', 'plain');

        expect($plain)->not->toBeNull()
            ->and($plain['type'])->toBe('unknown');
    });

    test('includes properties from trait methods without array shape annotation', function () {
        $basic = collect($this->analysis->properties)->firstWhere('name', 'basic');

        expect($basic)->not->toBeNull()
            ->and($basic['type'])->toBe('unknown');
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

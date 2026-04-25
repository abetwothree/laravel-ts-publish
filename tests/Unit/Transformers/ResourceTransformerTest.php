<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\Accounting\Http\Resources\InvoiceResource;
use Workbench\App\Http\Resources\AddressExtendsResource;
use Workbench\App\Http\Resources\AddressMixinResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\Admin\Store as AdminStoreResource;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\ChildSharedResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\DelegatingWithMixinResource;
use Workbench\App\Http\Resources\EmptyResource;
use Workbench\App\Http\Resources\EmptyWithMixinResource;
use Workbench\App\Http\Resources\EventLogResource;
use Workbench\App\Http\Resources\FqcnMixinResource;
use Workbench\App\Http\Resources\MediaTypeInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypeResource;
use Workbench\App\Http\Resources\MediaTypeUnknownResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Http\Resources\ServiceDeskResource;
use Workbench\App\Http\Resources\TraitSpreadCoverageResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Admin\Store as AdminStore;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\TrackingEvent;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;
use Workbench\App\Resources\DirectResource;
use Workbench\Blog\Http\Resources\ApiArticleResource;
use Workbench\Crm\Http\Resources\DealResource;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

describe('ResourceTransformer with PostResource', function () {
    test('resolves model class from @mixin docblock', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->modelClass)->toBe(Post::class);
    });

    test('transforms resource name', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->resourceName)->toBe('PostResource');
    });

    test('transforms basic property types from model columns', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties)
            ->toHaveKey('id')
            ->toHaveKey('title')
            ->toHaveKey('content');

        expect($data->properties['id']['type'])->toBe('number');
        expect($data->properties['title']['type'])->toBe('string');
        expect($data->properties['content']['type'])->toBe('string');
    });

    test('resolves EnumResource::make() to AsEnum type with tolki enabled', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status']['type'])->toBe('AsEnum<typeof Status>');
        expect($data->properties['visibility']['type'])->toBe('AsEnum<typeof Visibility> | null');
        expect($data->properties['priority']['type'])->toBe('AsEnum<typeof Priority> | null');
    });

    test('resolves new EnumResource() to AsEnum type with tolki enabled', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status_new']['type'])->toBe('AsEnum<typeof Status>');
        expect($data->properties['visibility_new']['type'])->toBe('AsEnum<typeof Visibility> | null');
        expect($data->properties['priority_new']['type'])->toBe('AsEnum<typeof Priority> | null');
    });

    test('resolves EnumResource::make() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status']['type'])->toBe('StatusType');
        expect($data->properties['visibility']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority']['type'])->toBe('PriorityType | null');
    });

    test('resolves new EnumResource() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status_new']['type'])->toBe('StatusType');
        expect($data->properties['visibility_new']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority_new']['type'])->toBe('PriorityType | null');
    });

    test('marks basic properties as non-optional', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['id']['optional'])->toBeFalse();
        expect($data->properties['title']['optional'])->toBeFalse();
        expect($data->properties['status']['optional'])->toBeFalse();
        expect($data->properties['status_new']['optional'])->toBeFalse();
    });

    test('generates correct filename', function () {
        $transformer = new ResourceTransformer(PostResource::class);

        expect($transformer->filename())->toBe('post-resource');
    });

    test('filePath contains Resources and PostResource', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->filePath)
            ->toContain('Resources')
            ->toContain('PostResource.php')
            ->not->toStartWith('/');
    });
});

describe('ResourceTransformer with UserResource', function () {
    test('resolves model class from #[TsResource(model:)] attribute', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->modelClass)->toBe(User::class);
    });

    test('resolves description from docblock', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->description)->toBe('User account resource.');
    });

    test('transforms whenLoaded as optional with relation type', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['profile'])
            ->toHaveKey('type')
            ->toHaveKey('optional');

        expect($data->properties['profile']['optional'])->toBeTrue();
    });

    test('transforms whenHas as optional', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['phone']['optional'])->toBeTrue();
    });

    test('transforms whenNotNull as optional', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['avatar']['optional'])->toBeTrue();
    });

    test('transforms whenCounted as optional number', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['posts_count']['type'])->toBe('number');
        expect($data->properties['posts_count']['optional'])->toBeTrue();
        expect($data->properties['comments_count']['type'])->toBe('number');
        expect($data->properties['comments_count']['optional'])->toBeTrue();
    });

    test('resolves EnumResource::make() to AsEnum type', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['role']['type'])->toBe('AsEnum<typeof Role> | null');
        expect($data->properties['role']['optional'])->toBeFalse();
    });

    test('resolves EnumResource::make() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['role']['type'])->toBe('RoleType | null');
    });

    test('resolves nested resource collection type', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['posts']['type'])->toBe('PostResource[]');
        expect($data->properties['posts']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer with CommentResource', function () {
    test('resolves model from @mixin docblock', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->modelClass)->toBe(Comment::class);
    });

    test('applies TsResourceCasts type overrides', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['metadata']['type'])->toBe('Record<string, unknown>');
    });

    test('applies TsResourceCasts optional override', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['flagged_at']['type'])->toBe('string | null');
        expect($data->properties['flagged_at']['optional'])->toBeTrue();
    });

    test('resolves nested resource make as optional', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['author']['type'])->toBe('UserResource');
        expect($data->properties['author']['optional'])->toBeTrue();

        expect($data->properties['post']['type'])->toBe('PostResource');
        expect($data->properties['post']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer with OrderResource', function () {
    test('resolves model from @mixin docblock', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->modelClass)->toBe(Order::class);
    });

    test('resolves EnumResource::make() for Order enums with AsEnum', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['status']['type'])->toBe('AsEnum<typeof OrderStatus>');
        expect($data->properties['currency']['type'])->toBe('AsEnum<typeof Currency>');
    });

    test('resolves EnumResource::make() for Order enums with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['status']['type'])->toBe('OrderStatusType');
        expect($data->properties['currency']['type'])->toBe('CurrencyType');
    });

    test('transforms when() as optional', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['paid_at']['optional'])->toBeTrue();
    });

    test('transforms whenCounted as optional number', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['items_count']['type'])->toBe('number');
        expect($data->properties['items_count']['optional'])->toBeTrue();
    });

    test('transforms whenAggregated as optional number', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['total_avg']['type'])->toBe('number');
        expect($data->properties['total_avg']['optional'])->toBeTrue();
    });

    test('transforms mergeWhen properties as optional', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties)->toHaveKey('shipped_at');
        expect($data->properties)->toHaveKey('delivered_at');

        expect($data->properties['shipped_at']['optional'])->toBeTrue();
        expect($data->properties['delivered_at']['optional'])->toBeTrue();
    });

    test('transforms whenLoaded for order items', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['items']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer imports', function () {
    test('PostResource has value imports for enum consts with tolki', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->valueImports)->toHaveKey('../enums');
        expect($data->valueImports['../enums'])->toContain('Priority');
        expect($data->valueImports['../enums'])->toContain('Status');
        expect($data->valueImports['../enums'])->toContain('Visibility');
    });

    test('PostResource has no type imports for enums with tolki', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        // Enum FQCNs are removed from type imports when tolki rewrites them to AsEnum
        expect($data->typeImports)->not->toHaveKey('../enums');
    });

    test('PostResource has type imports for enums with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('PriorityType');
        expect($data->typeImports['../enums'])->toContain('StatusType');
        expect($data->typeImports['../enums'])->toContain('VisibilityType');
        expect($data->valueImports)->toBeEmpty();
    });

    test('UserResource has type imports for nested resource', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->toHaveKey('./');
        expect($data->typeImports['./'])->toContain('PostResource');
    });

    test('UserResource has value imports for enum const', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->valueImports)->toHaveKey('../enums');
        expect($data->valueImports['../enums'])->toContain('Role');
    });

    test('CommentResource has type imports for nested resources', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->typeImports)->toHaveKey('./');
        expect($data->typeImports['./'])->toContain('PostResource');
        expect($data->typeImports['./'])->toContain('UserResource');
    });

    test('CommentResource has enum imports from inline relation filter (post_extended)', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        // post_extended = $this->post?->except(['created_at', 'updated_at']) includes enum-casted columns
        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('StatusType');
        expect($data->typeImports['../enums'])->toContain('VisibilityType');
        expect($data->typeImports['../enums'])->toContain('PriorityType');
        expect($data->valueImports)->toBeEmpty();
    });

    test('OrderResource has value imports for enum consts', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->valueImports)->toHaveKey('../enums');
        expect($data->valueImports['../enums'])->toContain('Currency');
        expect($data->valueImports['../enums'])->toContain('OrderStatus');
    });

    test('OrderResource has type imports for related model', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->typeImports)->toHaveKey('../models');
        expect($data->typeImports['../models'])->toContain('OrderItem');
    });

    test('UserResource has type imports for related model', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->toHaveKey('../models');
        expect($data->typeImports['../models'])->toContain('Profile');
    });
});

describe('ResourceTransformer with AddressResource', function () {
    test('resolves resourceName from TsResource name attribute', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->resourceName)->toBe('Address');
    });

    test('resolves description from TsResource description attribute', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->description)->toBe('Mailing address resource');
    });

    test('applies TsResourceCasts with custom import', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->properties)->toHaveKey('coordinates')
            ->and($data->properties['coordinates']['type'])->toBe('GeoPoint')
            ->and($data->typeImports)->toHaveKey('@/types/geo')
            ->and($data->typeImports['@/types/geo'])->toContain('GeoPoint');
    });
});

describe('ResourceTransformer modular imports', function () {
    test('PostResource has modular enum value imports with tolki', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(PostResource::class))->data();

        // Should have relative path imports instead of ../enums
        expect($data->valueImports)->not->toHaveKey('../enums');

        $hasEnumValueImport = false;
        foreach ($data->valueImports as $path => $names) {
            if (count(array_intersect($names, ['Status', 'Visibility', 'Priority'])) > 0) {
                $hasEnumValueImport = true;
            }
        }
        expect($hasEnumValueImport)->toBeTrue();
    });

    test('PostResource has modular enum type imports with tolki disabled', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('../enums');

        $hasEnumTypeImport = false;
        foreach ($data->typeImports as $path => $names) {
            if (count(array_intersect($names, ['StatusType', 'VisibilityType', 'PriorityType'])) > 0) {
                $hasEnumTypeImport = true;
            }
        }
        expect($hasEnumTypeImport)->toBeTrue();
    });

    test('UserResource has modular resource imports', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('./');

        $hasResourceImport = false;
        foreach ($data->typeImports as $path => $names) {
            if (in_array('PostResource', $names, true)) {
                $hasResourceImport = true;
            }
        }
        expect($hasResourceImport)->toBeTrue();
    });

    test('UserResource has modular model imports', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('../models');

        $hasModelImport = false;
        foreach ($data->typeImports as $path => $names) {
            if (in_array('Profile', $names, true)) {
                $hasModelImport = true;
            }
        }
        expect($hasModelImport)->toBeTrue();
    });
});

describe('ResourceTransformer with FqcnMixinResource', function () {
    test('resolves model class from FQCN @mixin docblock', function () {
        $data = (new ResourceTransformer(FqcnMixinResource::class))->data();

        expect($data->modelClass)->toBe(Order::class);
    });

    test('resolves property types from FQCN mixin model', function () {
        $data = (new ResourceTransformer(FqcnMixinResource::class))->data();

        expect($data->properties['id']['type'])->toBe('number')
            ->and($data->properties['total']['type'])->toContain('number');
    });
});

describe('ResourceTransformer TsCasts waterfall from model', function () {
    test('AddressResource inherits model TsCasts overrides for latitude and longitude', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        // Address model has #[TsCasts(['latitude' => 'number | null', 'longitude' => 'number | null'])]
        // These should flow through to the resource instead of the inferred 'string' (from decimal:7 cast)
        expect($data->properties['latitude']['type'])->toBe('number | null');
        expect($data->properties['longitude']['type'])->toBe('number | null');
    });

    test('model TsCasts does not add properties not present in toArray', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        // Address model has TsCasts for 'full_address' but it's not in AddressResource::toArray()
        expect($data->properties)->not->toHaveKey('full_address');
    });

    test('TsResourceCasts overrides model TsCasts for same property', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        // Comment model has #[TsCasts(['metadata' => 'Record<string, unknown>'])] on casts() method
        // CommentResource has #[TsResourceCasts(['metadata' => 'Record<string, unknown>'])]
        // TsResourceCasts should take priority (same value here, but pipeline precedence is verified)
        expect($data->properties['metadata']['type'])->toBe('Record<string, unknown>');
    });

    test('ProductResource inherits model TsCasts inline type for dimensions', function () {
        $data = (new ResourceTransformer(ProductResource::class))->data();

        // Product model has #[TsCasts(['dimensions' => '{ length: number; ... }'])] on casts() method
        expect($data->properties['dimensions']['type'])
            ->toBe('{ length: number; width: number; height: number; unit: "cm" | "in" }');
    });

    test('ProductResource inherits model TsCasts import for metadata', function () {
        $data = (new ResourceTransformer(ProductResource::class))->data();

        // Product model has TsCasts with import: '@js/types/product' for metadata
        expect($data->properties['metadata']['type'])->toBe('ProductMetadata | ProductJsonMetaData | null');
        expect($data->typeImports)->toHaveKey('@js/types/product');
        expect($data->typeImports['@js/types/product'])->toContain('ProductJsonMetaData')
            ->and($data->typeImports['@js/types/product'])->toContain('ProductMetadata');
    });

    test('AddressResource TsResourceCasts coordinates still applies', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        // TsResourceCasts adds 'coordinates' with GeoPoint type and import (not in toArray)
        expect($data->properties['coordinates']['type'])->toBe('GeoPoint');
        // TsResourceCasts adds 'bounds' with GeoBounds type from the same import path
        expect($data->properties['bounds']['type'])->toBe('GeoBounds');
        expect($data->typeImports)->toHaveKey('@/types/geo');
        expect($data->typeImports['@/types/geo'])->toContain('GeoPoint')
            ->and($data->typeImports['@/types/geo'])->toContain('GeoBounds');
    });

    test('ProfileResource inherits property-level TsCasts for timezone', function () {
        $data = (new ResourceTransformer(ProfileResource::class))->data();

        // Profile model has #[TsCasts(['timezone' => 'string'])] on the $casts property
        expect($data->properties['timezone']['type'])->toBe('string');
    });

    test('ProfileResource inherits class-level TsCasts for social_links', function () {
        $data = (new ResourceTransformer(ProfileResource::class))->data();

        // Profile model has class-level #[TsCasts(['social_links' => '{ twitter?: ... }'])]
        expect($data->properties['social_links']['type'])
            ->toBe('{ twitter?: string; github?: string; linkedin?: string; website?: string }');
    });

    test('resource without backing model skips model TsCasts gracefully', function () {
        $data = (new ResourceTransformer(EmptyResource::class))->data();

        expect($data->modelClass)->toBeNull();
        expect($data->properties)->toBeEmpty();
    });

    test('resource with model and no toArray generates model attribute properties', function () {
        $data = (new ResourceTransformer(EmptyWithMixinResource::class))->data();

        expect($data->modelClass)->toBe(User::class)
            ->and($data->properties)->not->toBeEmpty()
            ->and($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties)->toHaveKey('email');
    });

    test('resource with model delegating to parent generates model attribute properties', function () {
        $data = (new ResourceTransformer(DelegatingWithMixinResource::class))->data();

        expect($data->modelClass)->toBe(User::class)
            ->and($data->properties)->not->toBeEmpty()
            ->and($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties)->toHaveKey('email');
    });
});

describe('ResourceTransformer self-referencing resources', function () {
    test('self-referencing resource does not import itself', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        // CategoryResource references CategoryResource::make() and ::collection()
        // It should NOT appear in its own type imports
        foreach ($data->typeImports as $types) {
            expect($types)->not->toContain('CategoryResource');
        }
    });

    test('self-referencing resource still imports other referenced resources', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        // CategoryResource also references PostResource::collection()
        $hasPostImport = false;
        foreach ($data->typeImports as $types) {
            if (in_array('PostResource', $types, true)) {
                $hasPostImport = true;
            }
        }
        expect($hasPostImport)->toBeTrue();
    });

    test('self-referencing resource resolves self-reference property types', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent']['type'])->toBe('CategoryResource');
        expect($data->properties['children']['type'])->toBe('CategoryResource[]');
    });
});

describe('ResourceTransformer with parent::toArray spread', function () {
    test('ApiPostResource includes parent PostResource properties', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('title')
            ->and($data->properties)->toHaveKey('content')
            ->and($data->properties)->toHaveKey('status')
            ->and($data->properties)->toHaveKey('status_new')
            ->and($data->properties)->toHaveKey('visibility')
            ->and($data->properties)->toHaveKey('visibility_new')
            ->and($data->properties)->toHaveKey('priority')
            ->and($data->properties)->toHaveKey('priority_new');
    });

    test('ApiPostResource parent properties have correct types', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        expect($data->properties['id']['type'])->toBe('number');
        expect($data->properties['title']['type'])->toBe('string');
        expect($data->properties['content']['type'])->toBe('string');
    });

    test('child properties override parent properties with same key', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        // Parent has EnumResource::make() → StatusType, child has $this->status → StatusType
        expect($data->properties['status']['type'])->toBe('StatusType');
        expect($data->properties['visibility']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority']['type'])->toBe('PriorityType | null');
    });

    test('non-overridden _new enum resource properties flow through from parent', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        // Parent has new EnumResource() for _new keys, child does not override them
        expect($data->properties['status_new']['type'])->toBe('StatusType');
        expect($data->properties['visibility_new']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority_new']['type'])->toBe('PriorityType | null');
    });

    test('ApiPostResource has enum type imports from parent', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        $allTypes = array_merge(...array_values($data->typeImports));

        expect($allTypes)->toContain('StatusType')
            ->and($allTypes)->toContain('VisibilityType')
            ->and($allTypes)->toContain('PriorityType');
    });
});

describe('ResourceTransformer with trait method spread', function () {
    test('PostResource morphValue has string type from PHPDoc array shape', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties['morphValue']['type'])->toBe('string');
    });

    test('AddressResource morphValue has string type from PHPDoc array shape', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties['morphValue']['type'])->toBe('string');
    });

    test('ApiPostResource inherits morphValue with string type via parent::toArray spread', function () {
        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties['morphValue']['type'])->toBe('string');
    });
});

describe('ResourceTransformer with trait TsResourceCasts', function () {
    test('applies TsResourceCasts type override from trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->properties)->toHaveKey('location')
            ->and($data->properties['location']['type'])->toBe('GeoPoint');
    });

    test('generates import from TsResourceCasts on trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->typeImports)->toHaveKey('@/types/geo')
            ->and($data->typeImports['@/types/geo'])->toContain('GeoPoint');
    });

    test('adds new property from TsResourceCasts on trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->properties)->toHaveKey('extra')
            ->and($data->properties['extra']['type'])->toBe('Record<string, unknown>');
    });

    test('resolves multiline @return array shape types from trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->properties)->toHaveKey('firstName')
            ->and($data->properties['firstName']['type'])->toBe('string')
            ->and($data->properties)->toHaveKey('isActive')
            ->and($data->properties['isActive']['type'])->toBe('boolean');
    });
});

describe('ResourceTransformer convention-based model guess', function () {
    test('resolves model from naming convention when no @mixin or TsResource & resolves properties', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->modelClass)->toBe(Warehouse::class);

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties['id']['type'])->toBe('number')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties['name']['type'])->toBe('string');
    });

    test('convention guess does not override @mixin when present', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->modelClass)->toBe(Post::class);
    });

    test('resource without matching model stays null', function () {
        $data = (new ResourceTransformer(EmptyResource::class))->data();

        expect($data->modelClass)->toBeNull();
    });

    test('resolves model from convention in modularized namespace & resolves properties', function () {
        $data = (new ResourceTransformer(CrmUserResource::class))->data();

        expect($data->modelClass)->toBe(CrmUser::class);

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties['id']['type'])->toBe('number')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties['name']['type'])->toBe('string')
            ->and($data->properties)->toHaveKey('email')
            ->and($data->properties['email']['type'])->toBe('string');
    });
});

describe('ResourceTransformer convention guess edge cases', function () {
    test('returns null when resource is not in Http\Resources namespace', function () {
        $data = (new ResourceTransformer(DirectResource::class))->data();

        expect($data->modelClass)->toBeNull();
    });

    test('resolves model from subdirectory without Resource suffix', function () {
        $data = (new ResourceTransformer(AdminStoreResource::class))->data();

        expect($data->modelClass)->toBe(AdminStore::class);
    });

    test('resolves properties from subdirectory convention-guessed model', function () {
        $data = (new ResourceTransformer(AdminStoreResource::class))->data();

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties['id']['type'])->toBe('number')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties['name']['type'])->toBe('string');
    });
});

describe('ResourceTransformer UseResource attribute model guess', function () {
    test('resolves model from #[UseResource] attribute on model & resolves properties', function () {
        $data = (new ResourceTransformer(EventLogResource::class))->data();

        expect($data->modelClass)->toBe(TrackingEvent::class);

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('description');
    })->skip(
        ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResource'),
        'UseResource attribute requires Laravel 12+',
    );
});

describe('ResourceTransformer import collision deconfliction', function () {
    test('aliases colliding enum types and model types in modular mode', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(DealResource::class))->data();

        $allTypeImports = array_merge(...array_values($data->typeImports));

        // Enum type imports should be aliased
        expect($allTypeImports)->toContain('StatusType as AppStatusType')
            ->toContain('StatusType as CrmStatusType');

        // Model type imports should be aliased
        expect($allTypeImports)->toContain('User as AppUser')
            ->toContain('User as CrmUser');

        // Resource type imports should be aliased
        expect($allTypeImports)->toContain('UserResource as AppUserResource')
            ->toContain('UserResource as CrmUserResource');

        // Property types should be rewritten
        expect($data->properties['status']['type'])->toBe('AppStatusType');
        expect($data->properties['status_enum']['type'])->toBe('AppStatusType');
        expect($data->properties['crm_status']['type'])->toBe('CrmStatusType');
        expect($data->properties['crm_enum']['type'])->toBe('CrmStatusType');
        expect($data->properties['customer']['type'])->toBe('CrmUser');
        expect($data->properties['admin']['type'])->toBe('AppUser');
        expect($data->properties['customer_resource']['type'])->toBe('CrmUserResource');
        expect($data->properties['admin_resource']['type'])->toBe('AppUserResource');
    });

    test('aliases colliding types in non-modular mode without data loss', function () {
        config()->set('ts-publish.modular_publishing', false);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(DealResource::class))->data();

        // Both StatusType entries should appear (not collapsed by array_unique)
        $enumImports = $data->typeImports['../enums'] ?? [];
        expect($enumImports)->toContain('StatusType as AppStatusType')
            ->toContain('StatusType as CrmStatusType');

        // Both User entries should appear
        $modelImports = $data->typeImports['../models'] ?? [];
        expect($modelImports)->toContain('User as AppUser')
            ->toContain('User as CrmUser');

        // Both UserResource entries should appear
        $resourceImports = $data->typeImports['./'] ?? [];
        expect($resourceImports)->toContain('UserResource as AppUserResource')
            ->toContain('UserResource as CrmUserResource');
    });

    test('aliases enum type imports, value imports, and property types with tolki enabled', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums_use_tolki_package', true);

        $data = (new ResourceTransformer(DealResource::class))->data();

        $allTypeImports = array_merge(...array_values($data->typeImports));
        $allValueImports = array_merge(...array_values($data->valueImports));

        // Models should be aliased
        expect($allTypeImports)->toContain('User as AppUser')
            ->toContain('User as CrmUser');

        // Resources should be aliased
        expect($allTypeImports)->toContain('UserResource as AppUserResource')
            ->toContain('UserResource as CrmUserResource');

        // Enum type imports should be aliased (kept for direct access properties)
        expect($allTypeImports)->toContain('StatusType as AppStatusType')
            ->toContain('StatusType as CrmStatusType');

        // Enum value imports should be aliased (for EnumResource::make tolki rewrite)
        expect($allValueImports)->toContain('Status as AppStatus')
            ->toContain('Status as CrmStatus');

        // Direct access properties use aliased type names
        expect($data->properties['status']['type'])->toBe('AppStatusType');
        expect($data->properties['crm_status']['type'])->toBe('CrmStatusType');

        // EnumResource::make properties use aliased const names in AsEnum
        expect($data->properties['status_enum']['type'])->toBe('AsEnum<typeof AppStatus>');
        expect($data->properties['crm_enum']['type'])->toBe('AsEnum<typeof CrmStatus>');

        // Model and resource properties use aliased names
        expect($data->properties['customer']['type'])->toBe('CrmUser');
        expect($data->properties['admin']['type'])->toBe('AppUser');
        expect($data->properties['customer_resource']['type'])->toBe('CrmUserResource');
        expect($data->properties['admin_resource']['type'])->toBe('AppUserResource');
    });
});

describe('ResourceTransformer with ApiArticleResource (abstract parent + trait spreads)', function () {
    test('includes properties from parent CommonResource trait method spreads', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties)->toHaveKey('firstName')
            ->and($data->properties)->toHaveKey('isActive')
            ->and($data->properties)->toHaveKey('location')
            ->and($data->properties)->toHaveKey('flag');
    });

    test('resolves enum types with tolki enabled', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['status']['type'])->toBe('AsEnum<typeof ArticleStatus>')
            ->and($data->properties['content_type']['type'])->toBe('AsEnum<typeof ContentType>');
    });

    test('resolves enum types with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['status']['type'])->toBe('ArticleStatusType')
            ->and($data->properties['content_type']['type'])->toBe('ContentTypeType');
    });

    test('author from whenLoaded is optional', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['author']['optional'])->toBeTrue()
            ->and($data->properties['author']['type'])->toBe('User');
    });

    test('includes custom import from parent TsResourceCasts trait', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        $allTypes = array_merge(...array_values($data->typeImports));

        expect($allTypes)->toContain('GeoPoint');
    });

    test('resolves $this->only properties with Article model types', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['title']['type'])->toBe('string')
            ->and($data->properties['slug']['type'])->toBe('string')
            ->and($data->properties['excerpt']['type'])->toBe('string | null')
            ->and($data->properties['body']['type'])->toBe('string');
    });
});

describe('ResourceTransformer with union model accessor types', function () {
    test('accessor returning a union of two different models produces correct aliased type', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)
            ->toHaveKey('last_user_activity_by')
            ->toHaveKey('last_user_activity_by_typed')
            ->toHaveKey('last_user_activity_by_typed_short');

        // lastUserActivityBy is Attribute<CrmUser|User|null, never> on the backing Warehouse model.
        // Both classes have class_basename = 'User', so they must be aliased.
        expect($data->properties['last_user_activity_by']['type'])
            ->toBe('WorkbenchUser | CrmUser | null');
        expect($data->properties['last_user_activity_by_typed']['type'])
            ->toBe('WorkbenchUser | CrmUser | null');
        expect($data->properties['last_user_activity_by_typed_short']['type'])
            ->toBe('WorkbenchUser | CrmUser | null');
    });

    test('accessor returning a union of two different models generates import aliases', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->typeImports)->toHaveKey('../models')
            ->and($data->typeImports['../models'])->toContain('User as CrmUser')
            ->and($data->typeImports['../models'])->toContain('User as WorkbenchUser');
    });

    test('accessor union type with ->only() filter produces inline object type', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)->toHaveKey('last_user_activity_by_partial')
            ->and($data->properties['last_user_activity_by_partial']['type'])->toBe('{ id: number; name: string } | null');
    });

    test('accessor union type with ->except() filter produces inline object union type', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)->toHaveKey('last_user_activity_by_mostly');

        $type = $data->properties['last_user_activity_by_mostly']['type'];

        // The result is a union of two per-model inline objects followed by | null.
        // CrmUser (Workbench\Crm\Models\User) has: email, company, status, created_at, updated_at — NOT id or name.
        // The CrmStatus enum is aliased to CrmStatusType to avoid conflict with WorkbenchStatusType.
        expect($type)
            ->not->toBe('unknown')
            ->toContain('{ email: string; company: string | null; status: CrmStatusType; created_at: string | null; updated_at: string | null }')
            ->toEndWith('| null');
    });

    test('accessor returning a union of two enum types produces correct aliased type', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)
            ->toHaveKey('review_priority')
            ->toHaveKey('review_priority_typed')
            ->toHaveKey('review_priority_typed_short');

        // Workbench\App\Enums\Status is aliased to WorkbenchStatusType (conflicts with CrmStatus);
        // Workbench\App\Enums\Priority has no conflict so stays as PriorityType.
        expect($data->properties['review_priority']['type'])
            ->toBe('WorkbenchStatusType | PriorityType | null');
        expect($data->properties['review_priority_typed']['type'])
            ->toBe('WorkbenchStatusType | PriorityType | null');
        expect($data->properties['review_priority_typed_short']['type'])
            ->toBe('WorkbenchStatusType | PriorityType | null');
    });

    test('accessor returning a union of two enum types includes both in enums import', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        $enumImports = collect($data->typeImports)
            ->filter(fn ($types) => in_array('PriorityType', $types, true))
            ->first();

        expect($enumImports)
            ->not->toBeNull()
            ->toContain('PriorityType')
            ->toContain('StatusType as WorkbenchStatusType');
    });

    test('inline object from ->except() uses aliased enum name instead of base name', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        $type = $data->properties['last_user_activity_by_mostly']['type'];

        // The CrmUser inline shape has a 'status' property typed as CrmStatus enum.
        // That StatusType should be aliased to CrmStatusType to avoid conflict with WorkbenchStatusType.
        expect($type)->toContain('status: CrmStatusType');
    });
});

describe('ResourceTransformer inline model FQCN collision via ->only() filter', function () {
    test('model nested in ->only() inline object is aliased when it conflicts with another model', function () {
        $data = (new ResourceTransformer(ServiceDeskResource::class))->data();

        // ServiceDeskResource has:
        //   'crm_agent'       => $this->crmAgent   (Workbench\Crm\Models\User → CrmUser)
        //   'order_requester' => $this->order?->only(['user'])
        //
        // Order.user() is a BelongsTo to Workbench\App\Models\User.
        // Both User classes share class_basename "User" → conflict → WorkbenchUser / CrmUser aliases.
        //
        // The inlineModelFqcns fix ensures the "User" token *inside* the inline object is rewritten.
        expect($data->properties['order_requester']['type'])->toBe('{ user: WorkbenchUser } | null');
    });

    test('direct model reference alongside inline embedded model both receive aliases', function () {
        $data = (new ResourceTransformer(ServiceDeskResource::class))->data();

        // crm_agent is a nullable BelongsTo to Workbench\Crm\Models\User.
        // FK crm_agent_id is nullable in the DB → type is CrmUser | null.
        expect($data->properties['crm_agent']['type'])->toBe('CrmUser | null');
    });

    test('imports include aliased names for both conflicting User models', function () {
        $data = (new ResourceTransformer(ServiceDeskResource::class))->data();

        $modelImports = collect($data->typeImports)->flatten()->all();

        expect($modelImports)
            ->toContain('User as WorkbenchUser')
            ->toContain('User as CrmUser');
    });
});

describe('ResourceTransformer with #[TsExtends] attribute', function () {
    test('WarehouseResource has tsExtends from attribute, trait, and parent class', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->tsExtends)->toBe([
            'BaseResource',
            'ExtendableInterface',
            'Omit<Timestamps, "created_at" | "updated_at">',
            'ResourceRoutes',
            'Pick<Routable, "store" | "update">',
        ]);
    });

    test('WarehouseResource imports types from TsExtends', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->typeImports)->toHaveKey('@/types/base')
            ->and($data->typeImports['@/types/base'])->toContain('BaseResource');
    });

    test('resource without TsExtends has empty tsExtends', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe([]);
    });
});

describe('ResourceTransformer with config-based ts_extends', function () {
    test('applies global resource extends from config', function () {
        config()->set('ts-publish.ts_extends.resources', [
            'GlobalResource',
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toContain('GlobalResource');
    });

    test('applies config extends with import', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'ApiResource', 'import' => '@/types/api'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toContain('ApiResource')
            ->and($data->typeImports)->toHaveKey('@/types/api')
            ->and($data->typeImports['@/types/api'])->toContain('ApiResource');
    });

    test('merges attribute and config extends for resources', function () {
        config()->set('ts-publish.ts_extends.resources', [
            'GlobalResource',
        ]);

        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->tsExtends)->toContain('BaseResource')
            ->and($data->tsExtends)->toContain('GlobalResource');
    });

    test('config array entry without import key is collected without an import', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'GloballyKnown'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toContain('GloballyKnown')
            ->and($data->typeImports)->not->toHaveKey('GloballyKnown');
    });
});

describe('ResourceTransformer TsExtends deduplication and conflict resolution', function () {
    test('situation 1 — identical (extends, no-import) pairs are deduplicated', function () {
        config()->set('ts-publish.ts_extends.resources', ['SameType', 'SameType']);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['SameType']);
    });

    test('situation 1 — identical (extends, import) pairs from config are deduplicated', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'BaseItem', 'import' => '@/types/base'],
            ['extends' => 'BaseItem', 'import' => '@/types/base'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['BaseItem'])
            ->and($data->typeImports['@/types/base'])->toBe(['BaseItem']);
    });

    test('situation 2 — same type name from different import paths gets aliased', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'Routable', 'import' => '@/types/routing'],
            ['extends' => 'Routable', 'import' => '@/types/legacy'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['RoutingRoutable', 'LegacyRoutable'])
            ->and($data->typeImports['@/types/routing'])->toBe(['Routable as RoutingRoutable'])
            ->and($data->typeImports['@/types/legacy'])->toBe(['Routable as LegacyRoutable']);
    });

    test('situation 2 — alias is applied inside a generic extends clause via preg_replace', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'Pick<Routable, "store" | "update">', 'import' => '@/types/routing', 'types' => ['Routable']],
            ['extends' => 'Routable', 'import' => '@/types/legacy'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['Pick<RoutingRoutable, "store" | "update">', 'LegacyRoutable'])
            ->and($data->typeImports['@/types/routing'])->toBe(['Routable as RoutingRoutable'])
            ->and($data->typeImports['@/types/legacy'])->toBe(['Routable as LegacyRoutable']);
    });

    test('situation 3 — same type name from same import path is deduplicated to a single import', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'Routable', 'import' => '@/types/routing'],
            ['extends' => 'Pick<Routable, "store" | "update">', 'import' => '@/types/routing', 'types' => ['Routable']],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['Routable', 'Pick<Routable, "store" | "update">'])
            ->and($data->typeImports['@/types/routing'])->toBe(['Routable']);
    });
});

describe('ResourceTransformer TsExtends BFS trait deduplication', function () {
    test('trait shared by both child and parent is only processed once', function () {
        // ChildSharedResource uses SharedExtendsInterface directly AND extends BaseSharedResource
        // which also uses SharedExtendsInterface. The BFS $visited guard should prevent
        // SharedInterface from appearing twice in the extends list.
        $data = (new ResourceTransformer(ChildSharedResource::class))->data();

        expect($data->tsExtends)->toBe(['SharedInterface'])
            ->and($data->typeImports['@/types/shared'])->toBe(['SharedInterface']);
    });
});

describe('ResourceTransformer with InvoiceResource', function () {
    test('has enum imports from accessor model filter', function () {
        $data = (new ResourceTransformer(InvoiceResource::class))->data();

        // latest_payment_only = $this->latest_payment?->only(...) — accessor returns ?Payment
        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('PaymentStatusType');
        expect($data->typeImports['../enums'])->toContain('PaymentMethodType');
        expect($data->typeImports['../enums'])->toContain('CurrencyType');
    });

    test('has model imports from accessor model filter', function () {
        $data = (new ResourceTransformer(InvoiceResource::class))->data();

        // latest_payment_excluded = $this->latest_payment?->except(...) — Invoice relation remains
        expect($data->typeImports)->toHaveKey('../models');
        expect($data->typeImports['../models'])->toContain('Invoice');
    });
});

describe('ResourceTransformer with MediaTypeResource (model-less enum resource)', function () {
    test('enum-backed resource produces correct interface shape', function () {
        $data = (new ResourceTransformer(MediaTypeResource::class))->data();

        expect($data->properties)->toHaveKeys(['name', 'value', 'meta']);
        expect($data->properties['name']['type'])->toBe('string');
        expect($data->properties['value']['type'])->toBe('string');
        expect($data->properties['meta']['type'])
            ->toStartWith('{ ')
            ->toEndWith(' }')
            ->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });

    test('enum-backed resource has no model class', function () {
        $data = (new ResourceTransformer(MediaTypeResource::class))->data();

        expect($data->modelClass)->toBeNull();
    });

    test('enum-backed resource has no type imports', function () {
        $data = (new ResourceTransformer(MediaTypeResource::class))->data();

        expect($data->typeImports)->toBeEmpty();
    });
});

describe('ResourceTransformer with MediaTypeInstanceOfResource (instanceof guard)', function () {
    test('instanceof guard resolves same interface shape as @var docblock', function () {
        $data = (new ResourceTransformer(MediaTypeInstanceOfResource::class))->data();

        expect($data->properties)->toHaveKeys(['name', 'value', 'meta']);
        expect($data->properties['name']['type'])->toBe('string');
        expect($data->properties['value']['type'])->toBe('string');
        expect($data->properties['meta']['type'])
            ->toStartWith('{ ')
            ->toEndWith(' }')
            ->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });
});

describe('ResourceTransformer with MediaTypeUnknownResource (no type hints)', function () {
    test('produces unknown types when no @var or instanceof hints exist', function () {
        $data = (new ResourceTransformer(MediaTypeUnknownResource::class))->data();

        expect($data->properties)->toHaveKeys(['name', 'value', 'meta']);
        expect($data->properties['name']['type'])->toBe('unknown');
        expect($data->properties['value']['type'])->toBe('unknown');
    });
});

describe('ResourceTransformer with AddressMixinResource and AddressExtendsResource', function () {
    test('@mixin resolves model class from docblock', function () {
        $data = (new ResourceTransformer(AddressMixinResource::class))->data();

        expect($data->modelClass)->toBe(Address::class);
    });

    test('@extends resolves model class from docblock', function () {
        $data = (new ResourceTransformer(AddressExtendsResource::class))->data();

        expect($data->modelClass)->toBe(Address::class);
    });

    test('@mixin does not match when tag appears in description text', function () {
        $data = (new ResourceTransformer(AddressMixinResource::class))->data();

        // The description contains "@mixin" in prose but the regex should only match "* @mixin"
        expect($data->description)->toContain('@mixin');
        expect($data->modelClass)->toBe(Address::class);
    });

    test('@extends does not match when tag appears in description text', function () {
        $data = (new ResourceTransformer(AddressExtendsResource::class))->data();

        // The description contains "@extends" in prose but the regex should only match "* @extends"
        expect($data->description)->toContain('@extends');
        expect($data->modelClass)->toBe(Address::class);
    });

    test('both resources produce identical properties, imports, & value imports', function () {
        $mixinData = (new ResourceTransformer(AddressMixinResource::class))->data();
        $extendsData = (new ResourceTransformer(AddressExtendsResource::class))->data();

        expect($mixinData->properties)->toBe($extendsData->properties);
        expect($mixinData->typeImports)->toBe($extendsData->typeImports);
        expect($mixinData->valueImports)->toBe($extendsData->valueImports);
    });
});

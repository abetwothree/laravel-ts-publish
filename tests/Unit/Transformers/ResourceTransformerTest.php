<?php

use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\DelegatingWithMixinResource;
use Workbench\App\Http\Resources\EmptyResource;
use Workbench\App\Http\Resources\EmptyWithMixinResource;
use Workbench\App\Http\Resources\FqcnMixinResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Http\Resources\TraitSpreadCoverageResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

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
        expect($data->properties['visibility']['type'])->toBe('AsEnum<typeof Visibility>');
        expect($data->properties['priority']['type'])->toBe('AsEnum<typeof Priority>');
    });

    test('resolves EnumResource::make() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status']['type'])->toBe('StatusType');
        expect($data->properties['visibility']['type'])->toBe('VisibilityType');
        expect($data->properties['priority']['type'])->toBe('PriorityType');
    });

    test('marks basic properties as non-optional', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['id']['optional'])->toBeFalse();
        expect($data->properties['title']['optional'])->toBeFalse();
        expect($data->properties['status']['optional'])->toBeFalse();
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

        expect($data->properties['role']['type'])->toBe('AsEnum<typeof Role>');
        expect($data->properties['role']['optional'])->toBeFalse();
    });

    test('resolves EnumResource::make() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums_use_tolki_package', false);

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['role']['type'])->toBe('RoleType');
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

    test('CommentResource has no enum imports', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('../enums');
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
        expect($data->typeImports)->toHaveKey('@/types/geo');
        expect($data->typeImports['@/types/geo'])->toContain('GeoPoint');
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
            ->and($data->properties)->toHaveKey('visibility')
            ->and($data->properties)->toHaveKey('priority');
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
        expect($data->properties['visibility']['type'])->toBe('VisibilityType');
        expect($data->properties['priority']['type'])->toBe('PriorityType');
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

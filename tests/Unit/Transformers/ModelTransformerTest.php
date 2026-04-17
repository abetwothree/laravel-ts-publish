<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Workbench\Accounting\Models\Invoice;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Address;
use Workbench\App\Models\BaseSharedExtendableModel;
use Workbench\App\Models\Category;
use Workbench\App\Models\ChildSharedExtendableModel;
use Workbench\App\Models\CompositeComment;
use Workbench\App\Models\ExcludableModel;
use Workbench\App\Models\Image;
use Workbench\App\Models\ModelWithNestedTraitExtends;
use Workbench\App\Models\ModelWithParentExtends;
use Workbench\App\Models\ModelWithTraitExtends;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\StrictCompositeComment;
use Workbench\App\Models\StrictTaskAssignment;
use Workbench\App\Models\Tag;
use Workbench\App\Models\TaskAssignment;
use Workbench\App\Models\TrackingEvent;
use Workbench\App\Models\UntypedColumn;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;
use Workbench\App\Relations\CompositeMorphTo;
use Workbench\Crm\Models\Deal;

describe('ModelTransformer with User model', function () {
    test('transforms User model name and filePath', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->modelName)->toBe('User')
            ->and($data->filePath)->toContain('Models')
            ->and($data->filePath)->toContain('User.php')
            ->and($data->filePath)->not->toStartWith('/');
    });

    test('transforms User model columns', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->columns)
            ->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('email')
            ->toHaveKey('password')
            ->toHaveKey('role')
            ->toHaveKey('membership_level');

        // Enum columns resolve to their TypeType
        expect($data->columns['role']['type'])->toBe('RoleType | null');
        expect($data->columns['membership_level']['type'])->toBe('MembershipLevelType | null');
    });

    test('resolves DB column type from Attribute accessor get closure', function () {
        $data = (new ModelTransformer(User::class))->data();

        // The `name` column has an Attribute accessor with get: fn($value): string
        // It should resolve to 'string' from the closure return type, not 'Attribute'
        expect($data->columns['name']['type'])->toBe('string');
    });

    test('transforms User model with TsCasts overrides on casts method', function () {
        $data = (new ModelTransformer(User::class))->data();

        // TsCasts applied on the casts() method override the default type inference
        expect($data->columns['settings']['type'])->toBe('{ theme: "light" | "dark"; notifications: boolean; locale: string } | null');
        expect($data->columns['options']['type'])->toBe('Record<string, unknown> | null');
    });

    test('transforms User model enum imports', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('RoleType')
            ->and($data->typeImports['../enums'])->toContain('MembershipLevelType');
    });

    test('transforms User model relations', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->relations)
            ->toHaveKey('profile')
            ->toHaveKey('posts')
            ->toHaveKey('comments')
            ->toHaveKey('orders')
            ->toHaveKey('addresses')
            ->toHaveKey('teams')
            ->toHaveKey('owned_teams')
            ->toHaveKey('images');

        // HasOne → singular nullable, HasMany/BelongsToMany/MorphMany → array
        expect($data->relations['profile']['type'])->toBe('Profile | null');
        expect($data->relations['posts']['type'])->toBe('Post[]');
        expect($data->relations['teams']['type'])->toBe('Team[]');
        expect($data->relations['images']['type'])->toBe('Image[]');
    });

    test('transforms User model mutators', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->mutators)
            ->toHaveKey('initials')
            ->toHaveKey('is_premium')
            ->and($data->mutators['initials']['type'])->toBe('string')
            ->and($data->mutators['is_premium']['type'])->toBe('boolean');
    });

    test('transforms User model imports', function () {
        $data = (new ModelTransformer(User::class))->data();

        // Model imports should include related model types but not self
        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('Profile')
            ->and($data->typeImports['.'])->toContain('Post')
            ->and($data->typeImports['.'])->toContain('Image')
            ->and($data->typeImports['.'])->not->toContain('User');
    });
});

describe('ModelTransformer with Address model that has class-level TsCasts', function () {
    test('transforms Address model with class-level TsCasts', function () {
        $data = (new ModelTransformer(Address::class))->data();

        expect($data->modelName)->toBe('Address')
            ->and($data->columns['latitude']['type'])->toBe('number | null')
            ->and($data->columns['longitude']['type'])->toBe('number | null');
    });

    test('transforms Address model mutators', function () {
        $data = (new ModelTransformer(Address::class))->data();

        expect($data->mutators)
            ->toHaveKey('has_coordinates')
            ->not->toHaveKey('full_address')
            ->and($data->mutators['has_coordinates']['type'])->toBe('boolean');
    });

    test('transforms Address model appends with TsCasts override', function () {
        $data = (new ModelTransformer(Address::class))->data();

        // full_address is in $appends so it goes to appends, not mutators
        expect($data->appends)
            ->toHaveKey('full_address')
            ->and($data->appends['full_address']['type'])->toBe('string | null');
    });
});

describe('ModelTransformer with Product model that has TsCasts with custom imports', function () {
    test('transforms Product model with custom import types', function () {
        $data = (new ModelTransformer(Product::class))->data();

        expect($data->modelName)->toBe('Product');

        // dimensions uses an inline type override with no import
        expect($data->columns['dimensions']['type'])->toBe('{ length: number; width: number; height: number; unit: "cm" | "in" }');

        // metadata uses array-with-import syntax
        expect($data->columns['metadata']['type'])->toBe('ProductMetadata | ProductJsonMetaData | null');
    });

    test('Product model has custom imports from TsCasts', function () {
        $data = (new ModelTransformer(Product::class))->data();

        expect($data->typeImports)->toHaveKey('@js/types/product');

        // Should extract just the importable type names, not primitives or null
        $importedTypes = $data->typeImports['@js/types/product'];
        expect($importedTypes)->toContain('ProductMetadata')
            ->and($importedTypes)->toContain('ProductJsonMetaData')
            ->and($importedTypes)->not->toContain('null');
    });

    test('transforms Product model relations', function () {
        $data = (new ModelTransformer(Product::class))->data();

        expect($data->relations)
            ->toHaveKey('order_items')
            ->toHaveKey('tags')
            ->toHaveKey('images')
            ->and($data->relations['order_items']['type'])->toBe('OrderItem[]')
            ->and($data->relations['tags']['type'])->toBe('Tag[]')
            ->and($data->relations['images']['type'])->toBe('Image[]');
    });
});

describe('ModelTransformer with Post model that has method-level TsCasts', function () {
    test('transforms Post model with method-level TsCasts', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data->columns['metadata']['type'])->toBe('Record<string, {title: string, content: string}>');
    });

    test('transforms Post model enum imports', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])
            ->toContain('StatusType')
            ->toContain('VisibilityType')
            ->toContain('PriorityType');
    });
});

describe('ModelTransformer with Category model that has self-referencing relations', function () {
    test('transforms Category model with self-referencing relations', function () {
        $data = (new ModelTransformer(Category::class))->data();

        expect($data->relations)
            ->toHaveKey('parent')
            ->toHaveKey('children')
            ->toHaveKey('posts')
            ->and($data->relations['parent']['type'])->toBe('Category | null')
            ->and($data->relations['children']['type'])->toBe('Category[]')
            ->and($data->relations['posts']['type'])->toBe('Post[]');

        // Self-reference should NOT appear in model imports
        $modelImports = $data->typeImports['./'] ?? [];
        expect($modelImports)->not->toContain('Category');
    });
});

describe('ModelTransformer with Tag model that has MorphedByMany relations', function () {
    test('transforms Tag model with MorphedByMany relations', function () {
        $data = (new ModelTransformer(Tag::class))->data();

        expect($data->relations)
            ->toHaveKey('posts')
            ->toHaveKey('products')
            ->and($data->relations['posts']['type'])->toBe('Post[]')
            ->and($data->relations['products']['type'])->toBe('Product[]');
    });
});

describe('ModelTransformer with Invoice model from modules directory', function () {
    test('transforms Invoice model from modules directory', function () {
        $data = (new ModelTransformer(Invoice::class))->data();

        expect($data->modelName)->toBe('Invoice')
            ->and($data->columns)->toHaveKey('status')
            ->and($data->columns)->toHaveKey('total')
            ->and($data->columns['status']['type'])->toBe('InvoiceStatusType');

        expect($data->typeImports['../enums'])->toContain('InvoiceStatusType');

        expect($data->relations)
            ->toHaveKey('user')
            ->toHaveKey('payments')
            ->and($data->relations['user']['type'])->toBe('User')
            ->and($data->relations['payments']['type'])->toBe('Payment[]');
    });
});

describe('ModelTransformer with Order model that has complex TsCasts and multiple enum casts', function () {
    test('transforms Order model TsCasts inline types', function () {
        $data = (new ModelTransformer(Order::class))->data();

        expect($data->columns['shipping_address']['type'])->toBe('{ line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string }');
        expect($data->columns['billing_address']['type'])->toBe('{ line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string }');
    });

    test('transforms Order model enum casts', function () {
        $data = (new ModelTransformer(Order::class))->data();

        expect($data->columns['status']['type'])->toBe('OrderStatusType')
            ->and($data->columns['payment_method']['type'])->toBe('PaymentMethodType | null')
            ->and($data->columns['currency']['type'])->toBe('CurrencyType');

        expect($data->typeImports['../enums'])
            ->toContain('OrderStatusType')
            ->toContain('PaymentMethodType')
            ->toContain('CurrencyType');
    });
});

describe('ModelTransformer filename generation', function () {
    test('filename returns kebab-cased model name', function () {
        expect((new ModelTransformer(User::class))->filename())->toBe('user');
        expect((new ModelTransformer(Order::class))->filename())->toBe('order');
        expect((new ModelTransformer(Product::class))->filename())->toBe('product');
    });
});

describe('ModelTransformer with Profile model that has property-level TsCasts, write-only mutator, and old-style mutator', function () {
    test('transforms Profile model with property-level TsCasts', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        // Property-level #[TsCasts(['timezone' => 'string'])] should override
        expect($data->columns['timezone']['type'])->toBe('string');
    });

    test('transforms Profile model class-level TsCasts for columns', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        expect($data->columns['social_links']['type'])->toBe('{ twitter?: string; github?: string; linkedin?: string; website?: string }');
        expect($data->columns['settings']['type'])->toBe('{ notifications_enabled: boolean; theme: "light" | "dark"; language: string }');
    });

    test('transforms Profile model write-only mutator as unknown', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        // normalizedPhone is set-only — no get — should resolve to unknown
        expect($data->mutators)->toHaveKey('normalized_phone')
            ->and($data->mutators['normalized_phone']['type'])->toBe('unknown');
    });

    test('transforms Profile model old-style mutator', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        // getFormattedBioAttribute → formatted_bio
        expect($data->mutators)->toHaveKey('formatted_bio')
            ->and($data->mutators['formatted_bio']['type'])->toBe('string');
    });
});

describe('ModelTransformer with TrackingEvent model that has a helper method colliding with an old-style accessor', function () {
    test('falls back to old-style accessor when the camelCase method is not an Attribute accessor', function () {
        $data = (new ModelTransformer(TrackingEvent::class))->data();

        expect($data->mutators)
            ->toHaveKey('changes')
            ->and($data->mutators['changes']['type'])->toBe('{ attributes: Record<string, unknown>; old: Record<string, unknown> }');
    });
});

describe('ModelTransformer with User model respecting relationship_case config', function () {
    test('respects relationship_case config for relation names', function () {
        config()->set('ts-publish.models.relationship_case', 'snake');

        $data = (new ModelTransformer(User::class))->data();

        expect($data->relations)->toHaveKey('owned_teams');
    });
});

describe('ModelTransformer resolveRelativePath method', function () {
    test('resolveRelativePath returns vendor-relative path for files outside base_path', function () {
        $transformer = new ModelTransformer(User::class);

        // Use reflection to test the protected method
        $method = new ReflectionMethod($transformer, 'resolveRelativePath');

        // Simulate path outside base_path() but containing /vendor/
        $vendorPath = '/some/other/path/vendor/package/src/Model.php';
        $result = $method->invoke($transformer, $vendorPath);

        expect($result)->toBe('vendor/package/src/Model.php');
    });

    test('resolveRelativePath returns absolute path when outside base_path and no vendor', function () {
        $transformer = new ModelTransformer(User::class);

        $method = new ReflectionMethod($transformer, 'resolveRelativePath');

        $absolutePath = '/completely/different/path/Model.php';
        $result = $method->invoke($transformer, $absolutePath);

        expect($result)->toBe('/completely/different/path/Model.php');
    });
});

describe('ModelTransformer namespacePath', function () {
    test('computes namespacePath with strip prefix', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $transformer = new ModelTransformer(User::class);

        expect($transformer->namespacePath)->toBe('app/models');
    });

    test('computes namespacePath for module model with strip prefix', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $transformer = new ModelTransformer(Invoice::class);

        expect($transformer->namespacePath)->toBe('accounting/models');
    });
});

describe('ModelTransformer modular typeImports', function () {
    test('computes modular typeImports with relative paths for Invoice model', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Invoice::class))->data();

        // Invoice is in accounting/models
        // InvoiceStatus enum is in accounting/enums → ../enums
        // User model is in app/models → ../../app/models
        // Payment model is in accounting/models → . (same dir)
        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('InvoiceStatusType');

        expect($data->typeImports)->toHaveKey('../../app/models');
        expect($data->typeImports['../../app/models'])->toContain('User');

        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('Payment');
    });

    test('computes modular typeImports for User model with enum and model imports', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(User::class))->data();

        // User is in app/models
        // Role, MembershipLevel enums are in app/enums → ../enums
        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('RoleType')
            ->and($data->typeImports['../enums'])->toContain('MembershipLevelType');

        // Related models in the same namespace (Profile, Post, etc.) → . (same dir)
        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('Profile')
            ->and($data->typeImports['.'])->toContain('Post')
            ->and($data->typeImports['.'])->not->toContain('User');
    });
});

describe('ModelTransformer import alias resolution for duplicate names', function () {
    test('aliases model imports when two relations reference different models with the same class name', function () {
        // Deal relates to Crm\User (via customer) and App\User (via admin)
        $data = (new ModelTransformer(Deal::class))->data();

        // Both relations should be present with aliased type names
        expect($data->relations)->toHaveKey('customer')
            ->and($data->relations)->toHaveKey('admin');

        // Types should use relationship-based aliases since each FQCN has exactly one relation
        expect($data->relations['customer']['type'])->toBe('CustomerUser');
        expect($data->relations['admin']['type'])->toBe('AdminUser');

        // Imports should use "OriginalName as Alias" syntax
        $allImports = array_merge(...array_values($data->typeImports));
        expect($allImports)->toContain('User as CustomerUser')
            ->and($allImports)->toContain('User as AdminUser');
    });

    test('aliases enum imports when two enums from different namespaces share the same TypeScript type name', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        // Deal casts status to App\Enums\Status (→ StatusType)
        // and crm_status to Crm\Enums\Status (→ StatusType) — a genuine collision
        $data = (new ModelTransformer(Deal::class))->data();

        // Both columns should use namespace-prefixed aliases
        expect($data->columns['status']['type'])->toBe('AppStatusType');
        expect($data->columns['crm_status']['type'])->toBe('CrmStatusType');

        // Imports should use "as" aliasing syntax for the enum types
        $allImports = array_merge(...array_values($data->typeImports));
        expect($allImports)->toContain('StatusType as AppStatusType')
            ->and($allImports)->toContain('StatusType as CrmStatusType');
    });

    test('aliases model imports with correct relative paths', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Deal::class))->data();

        // Deal is in crm/models
        // Crm\User is in crm/models → . (same dir)
        // App\User is in app/models → ../../app/models
        expect($data->relations['customer']['type'])->toBe('CustomerUser');
        expect($data->relations['admin']['type'])->toBe('AdminUser');

        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('User as CustomerUser');

        expect($data->typeImports)->toHaveKey('../../app/models');
        expect($data->typeImports['../../app/models'])->toContain('User as AdminUser');
    });

    test('does not alias imports when there are no naming conflicts', function () {
        // Invoice has unique model names (User, Payment) — no conflicts
        $data = (new ModelTransformer(Invoice::class))->data();

        expect($data->relations['user']['type'])->toBe('User');
        expect($data->relations['payments']['type'])->toBe('Payment[]');

        // No "as" aliasing in imports
        $allImports = array_merge(...array_values($data->typeImports));

        foreach ($allImports as $importEntry) {
            expect($importEntry)->not->toContain(' as ');
        }
    });

    test('does not alias imports when model name does not conflict with self', function () {
        // User model imports Profile, Post, etc. — none named "User"
        $data = (new ModelTransformer(User::class))->data();

        $allImports = array_merge(...array_values($data->typeImports));

        foreach ($allImports as $importEntry) {
            expect($importEntry)->not->toContain(' as ');
        }
    });

    test('computeNamespacePrefix returns meaningful namespace segment', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $transformer = new ModelTransformer(Deal::class);

        $method = new ReflectionMethod($transformer, 'computeNamespacePrefix');

        // Crm\Models\User → strip 'Workbench\', skip 'Models' → 'Crm'
        expect($method->invoke($transformer, 'Workbench\\Crm\\Models\\User'))->toBe('Crm');

        // App\Models\User → strip 'Workbench\', skip 'Models' & 'App' → 'App' (fallback to first)
        expect($method->invoke($transformer, 'Workbench\\App\\Models\\User'))->toBe('App');

        // Accounting\Enums\InvoiceStatus → strip 'Workbench\', skip 'Enums' → 'Accounting'
        expect($method->invoke($transformer, 'Workbench\\Accounting\\Enums\\InvoiceStatus'))->toBe('Accounting');
    });
});

describe('ModelTransformer doc block descriptions', function () {
    test('reads class-level description from doc block', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->description)->toBe('Application user account');
    });

    test('returns empty description when no doc block on class', function () {
        $data = (new ModelTransformer(Order::class))->data();

        expect($data->description)->toBe('');
    });

    test('reads accessor description for column from doc block', function () {
        $data = (new ModelTransformer(User::class))->data();

        // name() has /** User name formatted with first letter capitalized */
        expect($data->columns['name']['description'])->toBe('User name formatted with first letter capitalized');
    });

    test('reads accessor description for mutator from doc block', function () {
        $data = (new ModelTransformer(User::class))->data();

        // initials() has /** User initials (e.g. "JD" for "John Doe") */
        expect($data->mutators['initials']['description'])->toBe('User initials (e.g. "JD" for "John Doe")');
    });

    test('reads relation description from doc block', function () {
        $data = (new ModelTransformer(User::class))->data();

        // images() has /** Polymorphic images (avatar gallery, etc.) */
        expect($data->relations['images']['description'])->toBe('Polymorphic images (avatar gallery, etc.)');
    });

    test('returns empty description for column without accessor doc block', function () {
        $data = (new ModelTransformer(User::class))->data();

        // email has no accessor method at all
        expect($data->columns['email']['description'])->toBe('');
    });

    test('returns empty description for relation without doc block', function () {
        $data = (new ModelTransformer(User::class))->data();

        // profile() has no doc block
        expect($data->relations['profile']['description'])->toBe('');
    });
});

describe('ModelTransformer HasEnums enum column/mutator properties', function () {
    test('populates enumColumns for Post model with enum-cast columns', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data->enumColumns)
            ->toHaveKey('status')
            ->toHaveKey('visibility')
            ->toHaveKey('priority');

        // status is not nullable
        expect($data->enumColumns['status'])->toBe(['constName' => 'Status', 'nullable' => false]);
        // visibility is nullable
        expect($data->enumColumns['visibility'])->toBe(['constName' => 'Visibility', 'nullable' => true]);
        // priority is nullable
        expect($data->enumColumns['priority'])->toBe(['constName' => 'Priority', 'nullable' => true]);
    });

    test('enumColumns is empty when enums_use_tolki_package is disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ModelTransformer(Post::class))->data();

        expect($data->enumColumns)->toBeEmpty();
    });

    test('enumColumns is empty for model with no enum casts', function () {
        $data = (new ModelTransformer(Address::class))->data();

        expect($data->enumColumns)->toBeEmpty();
    });

    test('enumMutators is empty for Post model (no enum-type mutators)', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data->enumMutators)->toBeEmpty();
    });

    test('uses aliased const names for Deal model enum columns', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Deal::class))->data();

        expect($data->enumColumns)
            ->toHaveKey('status')
            ->toHaveKey('crm_status');

        expect($data->enumColumns['status']['constName'])->toBe('AppStatus');
        expect($data->enumColumns['crm_status']['constName'])->toBe('CrmStatus');
    });

    test('does not add @tolki/enum import when enums_use_tolki_package is disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ModelTransformer(Post::class))->data();

        expect($data->typeImports)->not->toHaveKey('@tolki/enum');
    });

    test('adds enum const names to valueImports for Post model', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data->valueImports['../enums'])
            ->toContain('Status')
            ->toContain('Visibility')
            ->toContain('Priority');

        // Type names remain in typeImports
        expect($data->typeImports['../enums'])
            ->toContain('StatusType')
            ->toContain('VisibilityType')
            ->toContain('PriorityType');
    });

    test('adds aliased enum const names to valueImports for Deal model', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Deal::class))->data();

        $allValueImports = array_merge(...array_values($data->valueImports));
        expect($allValueImports)->toContain('Status as AppStatus')
            ->and($allValueImports)->toContain('Status as CrmStatus');
    });

    test('does not add enum const imports when enums_use_tolki_package is disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ModelTransformer(Post::class))->data();

        // Type names still in typeImports
        expect($data->typeImports['../enums'])
            ->toContain('StatusType');

        // valueImports should be empty
        expect($data->valueImports)->toBeEmpty();
    });

    test('TsCasts-overridden columns are excluded from enumColumns', function () {
        // Post has metadata with TsCasts override — should not appear in enumColumns
        $data = (new ModelTransformer(Post::class))->data();

        expect($data->enumColumns)->not->toHaveKey('metadata');
    });

    test('adds enum const names to valueImports in modular mode', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Post::class))->data();

        expect($data->valueImports['../enums'])
            ->toContain('Status')
            ->toContain('Visibility')
            ->toContain('Priority');
    });
});

describe('ModelTransformer with Warehouse model', function () {
    test('write-only accessor on DB column falls back to column type', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        // phone has a write-only Attribute (set only, no get) — falls back to DB column type
        expect($data->columns)->toHaveKey('phone')
            ->and($data->columns['phone']['type'])->toBe('string | null');
    });

    test('column cast to CastsAttributes returning a plain class tracks classFqcns', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        // CoordinateCast.get() returns Coordinate — a class with no TsType/CastsAttributes
        expect($data->columns)->toHaveKey('coordinate_data')
            ->and($data->columns['coordinate_data']['type'])->toBe('Coordinate | null');
    });

    test('appended attribute returning a plain class tracks classFqcns', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        expect($data->appends)->toHaveKey('location')
            ->and($data->appends['location']['type'])->toBe('Coordinate');
    });

    test('mutator returning a TsType class includes customImports', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        expect($data->mutators)->toHaveKey('menu_config')
            ->and($data->mutators['menu_config']['type'])->toContain('MenuSettingsType');

        expect($data->typeImports)->toHaveKey('@js/types/settings');
    });

    test('aliases conflicting enum types on appends and rewrites types', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Warehouse::class))->data();

        // Column status uses App\Enums\Status → aliased to AppStatusType
        expect($data->columns['status']['type'])->toBe('AppStatusType | null');
        // Appended current_crm_status uses Crm\Enums\Status → aliased to CrmStatusType
        expect($data->appends['current_crm_status']['type'])->toBe('CrmStatusType | null');
    });

    test('uses computeNamespacePrefix for model referenced by 2+ relations', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Warehouse::class))->data();

        // Crm\User has 2 relations (primaryContact, secondaryContact) → namespace prefix alias
        expect($data->relations['primary_contact']['type'])->toBe('CrmUser | null')
            ->and($data->relations['secondary_contact']['type'])->toBe('CrmUser | null');

        // App\User has 1 relation (manager) → relation-based alias
        expect($data->relations['manager']['type'])->toBe('ManagerUser | null');
    });
});

describe('ModelTransformer resolveMutatorType edge cases', function () {
    test('resolveMutatorType returns unknown when no matching accessor exists', function () {
        $transformer = new ModelTransformer(User::class);

        $method = new ReflectionMethod($transformer, 'resolveMutatorType');

        // Pass a name with no matching new-style or old-style accessor
        $result = $method->invoke($transformer, 'nonexistent_property');

        expect($result['type'])->toBe('unknown');
    });
});

describe('ModelTransformer rewriteTypeReferences defensive branches', function () {
    test('skips when relation key is not in relations map', function () {
        $transformer = new ModelTransformer(User::class);

        // Inject a model FQCN relation that references a relation not in $this->relations
        $prop = new ReflectionProperty($transformer, 'modelFqcnRelations');
        $relations = $prop->getValue($transformer);
        $relations['Fake\\Model\\Ghost'] = ['nonexistent_relation'];
        $prop->setValue($transformer, $relations);

        $aliasProp = new ReflectionProperty($transformer, 'importAliases');
        $aliases = $aliasProp->getValue($transformer);
        $aliases['Fake\\Model\\Ghost'] = 'AliasedGhost';
        $aliasProp->setValue($transformer, $aliases);

        $mapProp = new ReflectionProperty($transformer, 'modelFqcnMap');
        $map = $mapProp->getValue($transformer);
        $map['Fake\\Model\\Ghost'] = 'Ghost';
        $mapProp->setValue($transformer, $map);

        // Call rewriteTypeReferences — should not throw, just skip the nonexistent relation
        $method = new ReflectionMethod($transformer, 'rewriteTypeReferences');
        $method->invoke($transformer);

        // Relations should remain unchanged
        expect($transformer->relations)->not->toHaveKey('nonexistent_relation');
    });

    test('skips when FQCN has no mapped name in rewrite loop', function () {
        $transformer = new ModelTransformer(User::class);

        // Inject an import alias for a FQCN not tracked in either map
        $aliasProp = new ReflectionProperty($transformer, 'importAliases');
        $aliases = $aliasProp->getValue($transformer);
        $aliases['Fake\\Unmapped\\Thing'] = 'AliasedThing';
        $aliasProp->setValue($transformer, $aliases);

        // Call rewriteTypeReferences — should hit the continue for unmapped FQCN
        $method = new ReflectionMethod($transformer, 'rewriteTypeReferences');
        $method->invoke($transformer);

        // Nothing should have changed
        expect($transformer->columns)->not->toBeEmpty();
    });
});

describe('ModelTransformer nullable relations', function () {
    test('BelongsTo with non-nullable FK is not nullable', function () {
        $data = (new ModelTransformer(Post::class))->data();

        // Post.user_id is NOT NULL → author BelongsTo is not nullable
        expect($data->relations['author']['type'])->toBe('User');
    });

    test('BelongsTo with nullable FK is nullable', function () {
        $data = (new ModelTransformer(Post::class))->data();

        // Post.category_id is nullable → category_rel BelongsTo is nullable
        expect($data->relations['category_rel']['type'])->toBe('Category | null');
    });

    test('MorphTo with non-nullable morph columns is not nullable', function () {
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap([
            User::class,
            Post::class,
            Product::class,
            Image::class,
        ]);

        $data = (new ModelTransformer(Image::class))->data();

        // Image uses morphs('imageable') — NOT NULL columns
        // MorphTo targets resolved via inverse MorphMany scanning: Post, Product, User
        expect($data->relations['imageable']['type'])->toBe('Post | Product | User');
    });

    test('MorphTo with resolved targets includes target models in import map', function () {
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap([
            User::class,
            Post::class,
            Product::class,
            Image::class,
        ]);

        $data = (new ModelTransformer(Image::class))->data();

        // Each morph target should appear in the type imports (flattened values)
        $importedTypes = array_merge(...array_values($data->typeImports));

        expect($importedTypes)
            ->toContain('Post')
            ->toContain('Product')
            ->toContain('User');
    });

    test('MorphTo without resolved targets falls back to unknown', function () {
        // No buildMorphTargetMap() call — morph map is empty
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->relations['imageable']['type'])->toBe('unknown');
    });

    test('HasOne is always nullable', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->relations['profile']['type'])->toBe('Profile | null');
    });

    test('HasMany is never nullable', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->relations['posts']['type'])->toBe('Post[]');
    });

    test('Warehouse BelongsTo relations with nullable FKs are nullable', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        // All three FK columns (manager_id, primary_contact_id, secondary_contact_id) are nullable
        expect($data->relations['manager']['type'])->toContain('| null');
        expect($data->relations['primary_contact']['type'])->toContain('| null');
        expect($data->relations['secondary_contact']['type'])->toContain('| null');
    });

    test('nullable relations disabled via config', function () {
        config()->set('ts-publish.models.nullable_relations', false);

        $data = (new ModelTransformer(User::class))->data();

        // HasOne should NOT be nullable when feature is disabled
        expect($data->relations['profile']['type'])->toBe('Profile');
    });

    test('relation_nullability_map config overrides default strategy', function () {
        config()->set('ts-publish.models.relation_nullability_map', [
            HasOne::class => 'never',
        ]);

        $data = (new ModelTransformer(User::class))->data();

        // HasOne overridden to 'never' — should not be nullable
        expect($data->relations['profile']['type'])->toBe('Profile');
    });

    test('relation_nullability_map can make BelongsTo always nullable', function () {
        config()->set('ts-publish.models.relation_nullability_map', [
            BelongsTo::class => 'nullable',
        ]);

        $data = (new ModelTransformer(Post::class))->data();

        // Even with non-nullable FK, BelongsTo is now always nullable
        expect($data->relations['author']['type'])->toBe('User | null');
    });

    test('fk strategy on non-BelongsTo relation falls back to nullable', function () {
        config()->set('ts-publish.models.relation_nullability_map', [
            HasOne::class => 'fk',
        ]);

        $data = (new ModelTransformer(User::class))->data();

        // HasOne is not a BelongsTo, so isForeignKeyNullable guard returns true
        expect($data->relations['profile']['type'])->toBe('Profile | null');
    });

    test('morph strategy on non-MorphTo relation falls back to nullable', function () {
        config()->set('ts-publish.models.relation_nullability_map', [
            HasOne::class => 'morph',
        ]);

        $data = (new ModelTransformer(User::class))->data();

        // HasOne is not a MorphTo, so isMorphNullable guard returns true
        expect($data->relations['profile']['type'])->toBe('Profile | null');
    });
});

describe('ModelTransformer composite foreign keys', function () {
    test('BelongsTo with composite FK is nullable when any column is nullable', function () {
        $data = (new ModelTransformer(TaskAssignment::class))->data();

        // TaskAssignment.assignee uses composite FK ['team_id', 'category_id']
        // category_id is nullable, so the relation should be nullable
        expect($data->relations['assignee']['type'])->toBe('TaskOwner | null');
    });

    test('BelongsTo with composite FK is not nullable when all columns are non-nullable', function () {
        $data = (new ModelTransformer(StrictTaskAssignment::class))->data();

        // StrictTaskAssignment.assignee uses composite FK ['team_id', 'category_id']
        // Both columns are NOT NULL, so the relation should not be nullable
        expect($data->relations['assignee']['type'])->toBe('TaskOwner');
    });
});

describe('ModelTransformer composite morph foreign keys', function () {
    test('MorphTo with composite FK is nullable when any FK column is nullable', function () {
        config()->set('ts-publish.models.relation_nullability_map', [
            CompositeMorphTo::class => 'morph',
        ]);

        $data = (new ModelTransformer(CompositeComment::class))->data();

        // CompositeComment.commentable uses composite FK ['commentable_id_1', 'commentable_id_2']
        // commentable_id_2 is nullable, so the relation should be nullable
        // MorphTo is typed as 'unknown' since the related model is polymorphic
        expect($data->relations['commentable']['type'])->toBe('unknown | null');
    });

    test('MorphTo with composite FK is not nullable when all columns are non-nullable', function () {
        config()->set('ts-publish.models.relation_nullability_map', [
            CompositeMorphTo::class => 'morph',
        ]);

        $data = (new ModelTransformer(StrictCompositeComment::class))->data();

        // StrictCompositeComment.commentable uses composite FK ['commentable_id_1', 'commentable_id_2']
        // All FK columns and commentable_type are NOT NULL
        // MorphTo is typed as 'unknown' since the related model is polymorphic
        expect($data->relations['commentable']['type'])->toBe('unknown');
    });
});

describe('ModelTransformer #[TsExclude] attribute', function () {
    test('excludes mutators with #[TsExclude] on the accessor method', function () {
        $data = (new ModelTransformer(ExcludableModel::class))->data();

        expect($data->mutators)->toHaveKey('display_name')
            ->and($data->mutators)->not->toHaveKey('secret_token');
    });

    test('excludes old-style mutators with #[TsExclude] on the getter method', function () {
        $data = (new ModelTransformer(ExcludableModel::class))->data();

        expect($data->mutators)->not->toHaveKey('legacy_token');
    });

    test('excludes relations with #[TsExclude] on the relation method', function () {
        $data = (new ModelTransformer(ExcludableModel::class))->data();

        expect($data->relations)->toHaveKey('posts')
            ->and($data->relations)->not->toHaveKey('comments');
    });
});

describe('ModelTransformer with #[TsExtends] attribute', function () {
    test('Warehouse model has tsExtends from attribute', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        expect($data->tsExtends)->toHaveCount(2)
            ->and($data->tsExtends[0])->toBe('HasTimestamps')
            ->and($data->tsExtends[1])->toBe('Pick<Auditable, "created_by" | "updated_by">');
    });

    test('Warehouse model imports types from TsExtends', function () {
        $data = (new ModelTransformer(Warehouse::class))->data();

        expect($data->typeImports)->toHaveKey('@/types/common')
            ->and($data->typeImports['@/types/common'])->toContain('HasTimestamps')
            ->and($data->typeImports)->toHaveKey('@/types/audit')
            ->and($data->typeImports['@/types/audit'])->toContain('Auditable');
    });

    test('model without TsExtends has empty tsExtends', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toBe([]);
    });
});

describe('ModelTransformer with config-based ts_extends', function () {
    test('applies global model extends from config', function () {
        config()->set('ts-publish.ts_extends.models', [
            'GlobalBase',
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toContain('GlobalBase');
    });

    test('applies config extends with import', function () {
        config()->set('ts-publish.ts_extends.models', [
            ['extends' => 'Trackable', 'import' => '@/types/tracking'],
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toContain('Trackable')
            ->and($data->typeImports)->toHaveKey('@/types/tracking')
            ->and($data->typeImports['@/types/tracking'])->toContain('Trackable');
    });

    test('merges attribute and config extends', function () {
        config()->set('ts-publish.ts_extends.models', [
            'GlobalBase',
        ]);

        $data = (new ModelTransformer(Warehouse::class))->data();

        expect($data->tsExtends)->toContain('HasTimestamps')
            ->and($data->tsExtends)->toContain('Pick<Auditable, "created_by" | "updated_by">')
            ->and($data->tsExtends)->toContain('GlobalBase');
    });

    test('config with explicit types for generic extends', function () {
        config()->set('ts-publish.ts_extends.models', [
            ['extends' => 'Omit<Auditable, "deleted_at">', 'import' => '@/types/audit', 'types' => ['Auditable']],
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toContain('Omit<Auditable, "deleted_at">')
            ->and($data->typeImports)->toHaveKey('@/types/audit')
            ->and($data->typeImports['@/types/audit'])->toContain('Auditable');
    });

    test('config array entry without import key is collected without an import', function () {
        config()->set('ts-publish.ts_extends.models', [
            ['extends' => 'GloballyKnown'],
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toContain('GloballyKnown')
            ->and($data->typeImports)->not->toHaveKey('GloballyKnown');
    });
});

describe('ModelTransformer TsExtends deduplication and conflict resolution', function () {
    test('situation 1 — identical (extends, no-import) pairs are deduplicated', function () {
        config()->set('ts-publish.ts_extends.models', ['SameType', 'SameType']);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toBe(['SameType']);
    });

    test('situation 2 — same type name from different import paths gets aliased', function () {
        config()->set('ts-publish.ts_extends.models', [
            ['extends' => 'Trackable', 'import' => '@/types/tracking'],
            ['extends' => 'Trackable', 'import' => '@/types/legacy'],
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toBe(['TrackingTrackable', 'LegacyTrackable'])
            ->and($data->typeImports['@/types/tracking'])->toBe(['Trackable as TrackingTrackable'])
            ->and($data->typeImports['@/types/legacy'])->toBe(['Trackable as LegacyTrackable']);
    });

    test('situation 2 — alias is applied inside a generic extends clause via preg_replace', function () {
        config()->set('ts-publish.ts_extends.models', [
            ['extends' => 'Pick<Trackable, "created_by">', 'import' => '@/types/tracking', 'types' => ['Trackable']],
            ['extends' => 'Trackable', 'import' => '@/types/legacy'],
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toBe(['Pick<TrackingTrackable, "created_by">', 'LegacyTrackable'])
            ->and($data->typeImports['@/types/tracking'])->toBe(['Trackable as TrackingTrackable'])
            ->and($data->typeImports['@/types/legacy'])->toBe(['Trackable as LegacyTrackable']);
    });

    test('situation 3 — same type name from same import path is deduplicated to a single import', function () {
        config()->set('ts-publish.ts_extends.models', [
            ['extends' => 'Trackable', 'import' => '@/types/tracking'],
            ['extends' => 'Pick<Trackable, "created_by">', 'import' => '@/types/tracking', 'types' => ['Trackable']],
        ]);

        $data = (new ModelTransformer(User::class))->data();

        expect($data->tsExtends)->toBe(['Trackable', 'Pick<Trackable, "created_by">'])
            ->and($data->typeImports['@/types/tracking'])->toBe(['Trackable']);
    });
});

describe('ModelTransformer TsExtends BFS inheritance traversal', function () {
    test('picks up #[TsExtends] from a used trait', function () {
        $data = (new ModelTransformer(ModelWithTraitExtends::class))->data();

        expect($data->tsExtends)->toContain('TraitInterface')
            ->and($data->typeImports)->toHaveKey('@/types/model-trait')
            ->and($data->typeImports['@/types/model-trait'])->toContain('TraitInterface');
    });

    test('picks up #[TsExtends] from a nested trait (trait-of-trait)', function () {
        $data = (new ModelTransformer(ModelWithNestedTraitExtends::class))->data();

        expect($data->tsExtends)->toContain('TraitInterface')
            ->and($data->typeImports)->toHaveKey('@/types/model-trait')
            ->and($data->typeImports['@/types/model-trait'])->toContain('TraitInterface');
    });

    test('picks up #[TsExtends] from a parent class', function () {
        $data = (new ModelTransformer(ModelWithParentExtends::class))->data();

        expect($data->tsExtends)->toContain('ParentModelInterface')
            ->and($data->typeImports)->toHaveKey('@/types/model-parent')
            ->and($data->typeImports['@/types/model-parent'])->toContain('ParentModelInterface');
    });

    test('BFS visited guard prevents duplicate extends when trait is shared by model and parent', function () {
        // ChildSharedExtendableModel uses SharedExtendsTrait directly AND extends
        // BaseSharedExtendableModel which also uses SharedExtendsTrait. The BFS $visited
        // guard should prevent SharedModelInterface from appearing twice.
        $data = (new ModelTransformer(ChildSharedExtendableModel::class))->data();

        expect($data->tsExtends)->toBe(['SharedModelInterface'])
            ->and($data->typeImports['@/types/shared-model'])->toBe(['SharedModelInterface']);
    });

    test('parent model itself only has its own extends, not inheriting upward', function () {
        $data = (new ModelTransformer(BaseSharedExtendableModel::class))->data();

        expect($data->tsExtends)->toBe(['SharedModelInterface']);
    });
});

describe('Image model @return Attribute<> docblock accessor resolution', function () {
    // Already-working coverage
    test('extension resolves string|null from docblock', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('extension')
            ->and($data->mutators['extension']['type'])->toBe('string | null');
    });

    test('size resolves number from docblock', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('size')
            ->and($data->mutators['size']['type'])->toBe('number');
    });

    // Multi-part union
    test('flexibleId resolves string|int|null union from docblock', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('flexible_id')
            ->and($data->mutators['flexible_id']['type'])->toBe('string | number | null');
    });

    // Nullable shorthand ?T
    test('optionalLabel resolves ?string to string | null from docblock', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('optional_label')
            ->and($data->mutators['optional_label']['type'])->toBe('string | null');
    });

    // Enum FQCN
    test('statusFromDocblock resolves enum FQCN to StatusType | null', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('status_from_docblock')
            ->and($data->mutators['status_from_docblock']['type'])->toBe('StatusType | null');

        expect($data->typeImports)->toHaveKey('../enums');
        expect($data->typeImports['../enums'])->toContain('StatusType');
    });

    // Model FQCN
    test('uploaderFromDocblock resolves model FQCN to User | null', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('uploader_from_docblock')
            ->and($data->mutators['uploader_from_docblock']['type'])->toBe('User | null');

        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('User');
    });

    // #[TsType] class with import
    test('configFromDocblock resolves #[TsType] class to MenuSettingsType with import', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('config_from_docblock')
            ->and($data->mutators['config_from_docblock']['type'])->toBe('MenuSettingsType');

        expect($data->typeImports)->toHaveKey('@js/types/settings');
        expect($data->typeImports['@js/types/settings'])->toContain('MenuSettingsType');
    });

    // Arrayable class
    test('dataFromDocblock resolves Arrayable class to unknown[]', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('data_from_docblock')
            ->and($data->mutators['data_from_docblock']['type'])->toBe('unknown[]');
    });

    // __toString class
    test('labelFromDocblock resolves class with __toString to string', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('label_from_docblock')
            ->and($data->mutators['label_from_docblock']['type'])->toBe('string');
    });

    // Edge cases
    test('accessor with no docblock resolves to unknown', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('no_docblock_accessor')
            ->and($data->mutators['no_docblock_accessor']['type'])->toBe('unknown');
    });

    test('accessor with @return string docblock (not Attribute<>) resolves to string | null', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('wrong_format_docblock')
            ->and($data->mutators['wrong_format_docblock']['type'])->toBe('string | null');
    });

    test('positive-int resolves to number via partial map match', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('positive_int_accessor')
            ->and($data->mutators['positive_int_accessor']['type'])->toBe('number');
    });

    test('numeric-string resolves to string via exact map match', function () {
        $data = (new ModelTransformer(Image::class))->data();

        expect($data->mutators)->toHaveKey('numeric_string_accessor')
            ->and($data->mutators['numeric_string_accessor']['type'])->toBe('string');
    });
});

describe('ModelTransformer with UntypedColumn model (unknown-type fallback paths)', function () {
    test('accessor on untyped column falls back via attribute match arm', function () {
        $data = (new ModelTransformer(UntypedColumn::class))->data();

        // accessor_col has Attribute accessor with no type hint on untyped column →
        // resolver returns unknown → transformer fallback fires
        expect($data->columns)->toHaveKey('accessor_col')
            ->and($data->columns['accessor_col']['type'])->toBe('unknown | null');
    });

    test('untyped column with no cast falls back via default match arm', function () {
        $data = (new ModelTransformer(UntypedColumn::class))->data();

        // cast_col has no accessor and no cast on untyped column →
        // resolver returns unknown → transformer fallback fires
        expect($data->columns)->toHaveKey('cast_col')
            ->and($data->columns['cast_col']['type'])->toBe('unknown | null');
    });

    test('nullable untyped column appends null to type', function () {
        $data = (new ModelTransformer(UntypedColumn::class))->data();

        // All untyped SQLite columns are nullable; the fallback returns 'unknown'
        // which doesn't contain 'null', so the transformer appends ' | null'
        expect($data->columns['nullable_accessor_col']['type'])->toBe('unknown | null');
    });
});

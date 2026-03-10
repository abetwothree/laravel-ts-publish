<?php

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Workbench\Accounting\Models\Invoice;
use Workbench\App\Models\Address;
use Workbench\App\Models\Category;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Tag;
use Workbench\App\Models\TrackingEvent;
use Workbench\App\Models\User;
use Workbench\Crm\Models\Deal;

describe('ModelTransformer with User model', function () {
    test('transforms User model name and filePath', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data['modelName'])->toBe('User')
            ->and($data['filePath'])->toContain('Models')
            ->and($data['filePath'])->toContain('User.php')
            ->and($data['filePath'])->not->toStartWith('/');
    });

    test('transforms User model columns', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data['columns'])
            ->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('email')
            ->toHaveKey('password')
            ->toHaveKey('role')
            ->toHaveKey('membership_level');

        // Enum columns resolve to their TypeType
        expect($data['columns']['role'])->toBe('RoleType | null');
        expect($data['columns']['membership_level'])->toBe('MembershipLevelType | null');
    });

    test('resolves DB column type from Attribute accessor get closure', function () {
        $data = (new ModelTransformer(User::class))->data();

        // The `name` column has an Attribute accessor with get: fn($value): string
        // It should resolve to 'string' from the closure return type, not 'Attribute'
        expect($data['columns']['name'])->toBe('string');
    });

    test('transforms User model with TsCasts overrides on casts method', function () {
        $data = (new ModelTransformer(User::class))->data();

        // TsCasts applied on the casts() method override the default type inference
        expect($data['columns']['settings'])->toBe('{ theme: "light" | "dark"; notifications: boolean; locale: string } | null');
        expect($data['columns']['options'])->toBe('Record<string, unknown> | null');
    });

    test('transforms User model enum imports', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data['resolvedImports'])->toHaveKey('../enums');
        expect($data['resolvedImports']['../enums'])->toContain('RoleType')
            ->and($data['resolvedImports']['../enums'])->toContain('MembershipLevelType');
    });

    test('transforms User model relations', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data['relations'])
            ->toHaveKey('profile')
            ->toHaveKey('posts')
            ->toHaveKey('comments')
            ->toHaveKey('orders')
            ->toHaveKey('addresses')
            ->toHaveKey('teams')
            ->toHaveKey('owned_teams')
            ->toHaveKey('images');

        // HasOne → singular, HasMany/BelongsToMany/MorphMany → array
        expect($data['relations']['profile'])->toBe('Profile');
        expect($data['relations']['posts'])->toBe('Post[]');
        expect($data['relations']['teams'])->toBe('Team[]');
        expect($data['relations']['images'])->toBe('Image[]');
    });

    test('transforms User model mutators', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data['mutators'])
            ->toHaveKey('initials')
            ->toHaveKey('is_premium')
            ->and($data['mutators']['initials'])->toBe('string')
            ->and($data['mutators']['is_premium'])->toBe('boolean');
    });

    test('transforms User model imports', function () {
        $data = (new ModelTransformer(User::class))->data();

        // Model imports should include related model types but not self
        expect($data['resolvedImports'])->toHaveKey('./');
        expect($data['resolvedImports']['./'])->toContain('Profile')
            ->and($data['resolvedImports']['./'])->toContain('Post')
            ->and($data['resolvedImports']['./'])->toContain('Image')
            ->and($data['resolvedImports']['./'])->not->toContain('User');
    });
});

describe('ModelTransformer with Address model that has class-level TsCasts', function () {
    test('transforms Address model with class-level TsCasts', function () {
        $data = (new ModelTransformer(Address::class))->data();

        expect($data['modelName'])->toBe('Address')
            ->and($data['columns']['latitude'])->toBe('number | null')
            ->and($data['columns']['longitude'])->toBe('number | null');
    });

    test('transforms Address model mutators', function () {
        $data = (new ModelTransformer(Address::class))->data();

        expect($data['mutators'])
            ->toHaveKey('has_coordinates')
            ->and($data['mutators']['has_coordinates'])->toBe('boolean');
    });

    test('transforms Address model mutator with TsCasts override', function () {
        $data = (new ModelTransformer(Address::class))->data();

        // full_address mutator has a TsCasts override from the class-level attribute
        expect($data['mutators'])
            ->toHaveKey('full_address')
            ->and($data['mutators']['full_address'])->toBe('string | null');
    });
});

describe('ModelTransformer with Product model that has TsCasts with custom imports', function () {
    test('transforms Product model with custom import types', function () {
        $data = (new ModelTransformer(Product::class))->data();

        expect($data['modelName'])->toBe('Product');

        // dimensions uses an inline type override with no import
        expect($data['columns']['dimensions'])->toBe('{ length: number; width: number; height: number; unit: "cm" | "in" }');

        // metadata uses array-with-import syntax
        expect($data['columns']['metadata'])->toBe('ProductMetadata | ProductJsonMetaData | null');
    });

    test('Product model has custom imports from TsCasts', function () {
        $data = (new ModelTransformer(Product::class))->data();

        expect($data['resolvedImports'])->toHaveKey('@js/types/product');

        // Should extract just the importable type names, not primitives or null
        $importedTypes = $data['resolvedImports']['@js/types/product'];
        expect($importedTypes)->toContain('ProductMetadata')
            ->and($importedTypes)->toContain('ProductJsonMetaData')
            ->and($importedTypes)->not->toContain('null');
    });

    test('transforms Product model relations', function () {
        $data = (new ModelTransformer(Product::class))->data();

        expect($data['relations'])
            ->toHaveKey('order_items')
            ->toHaveKey('tags')
            ->toHaveKey('images')
            ->and($data['relations']['order_items'])->toBe('OrderItem[]')
            ->and($data['relations']['tags'])->toBe('Tag[]')
            ->and($data['relations']['images'])->toBe('Image[]');
    });
});

describe('ModelTransformer with Post model that has method-level TsCasts', function () {
    test('transforms Post model with method-level TsCasts', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data['columns']['metadata'])->toBe('Record<string, {title: string, content: string}>');
    });

    test('transforms Post model enum imports', function () {
        $data = (new ModelTransformer(Post::class))->data();

        expect($data['resolvedImports'])->toHaveKey('../enums');
        expect($data['resolvedImports']['../enums'])
            ->toContain('StatusType')
            ->toContain('VisibilityType')
            ->toContain('PriorityType');
    });
});

describe('ModelTransformer with Category model that has self-referencing relations', function () {
    test('transforms Category model with self-referencing relations', function () {
        $data = (new ModelTransformer(Category::class))->data();

        expect($data['relations'])
            ->toHaveKey('parent')
            ->toHaveKey('children')
            ->toHaveKey('posts')
            ->and($data['relations']['parent'])->toBe('Category')
            ->and($data['relations']['children'])->toBe('Category[]')
            ->and($data['relations']['posts'])->toBe('Post[]');

        // Self-reference should NOT appear in model imports
        $modelImports = $data['resolvedImports']['./'] ?? [];
        expect($modelImports)->not->toContain('Category');
    });
});

describe('ModelTransformer with Tag model that has MorphedByMany relations', function () {
    test('transforms Tag model with MorphedByMany relations', function () {
        $data = (new ModelTransformer(Tag::class))->data();

        expect($data['relations'])
            ->toHaveKey('posts')
            ->toHaveKey('products')
            ->and($data['relations']['posts'])->toBe('Post[]')
            ->and($data['relations']['products'])->toBe('Product[]');
    });
});

describe('ModelTransformer with Invoice model from modules directory', function () {
    test('transforms Invoice model from modules directory', function () {
        $data = (new ModelTransformer(Invoice::class))->data();

        expect($data['modelName'])->toBe('Invoice')
            ->and($data['columns'])->toHaveKey('status')
            ->and($data['columns'])->toHaveKey('total')
            ->and($data['columns']['status'])->toBe('InvoiceStatusType');

        expect($data['resolvedImports']['../enums'])->toContain('InvoiceStatusType');

        expect($data['relations'])
            ->toHaveKey('user')
            ->toHaveKey('payments')
            ->and($data['relations']['user'])->toBe('User')
            ->and($data['relations']['payments'])->toBe('Payment[]');
    });
});

describe('ModelTransformer with Order model that has complex TsCasts and multiple enum casts', function () {
    test('transforms Order model TsCasts inline types', function () {
        $data = (new ModelTransformer(Order::class))->data();

        expect($data['columns']['shipping_address'])->toBe('{ line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string }');
        expect($data['columns']['billing_address'])->toBe('{ line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string }');
    });

    test('transforms Order model enum casts', function () {
        $data = (new ModelTransformer(Order::class))->data();

        expect($data['columns']['status'])->toBe('OrderStatusType')
            ->and($data['columns']['payment_method'])->toBe('PaymentMethodType | null')
            ->and($data['columns']['currency'])->toBe('CurrencyType');

        expect($data['resolvedImports']['../enums'])
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
        expect($data['columns']['timezone'])->toBe('string');
    });

    test('transforms Profile model class-level TsCasts for columns', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        expect($data['columns']['social_links'])->toBe('{ twitter?: string; github?: string; linkedin?: string; website?: string }');
        expect($data['columns']['settings'])->toBe('{ notifications_enabled: boolean; theme: "light" | "dark"; language: string }');
    });

    test('transforms Profile model write-only mutator as unknown', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        // normalizedPhone is set-only — no get — should resolve to unknown
        expect($data['mutators'])->toHaveKey('normalized_phone')
            ->and($data['mutators']['normalized_phone'])->toBe('unknown');
    });

    test('transforms Profile model old-style mutator', function () {
        $data = (new ModelTransformer(Profile::class))->data();

        // getFormattedBioAttribute → formatted_bio
        expect($data['mutators'])->toHaveKey('formatted_bio')
            ->and($data['mutators']['formatted_bio'])->toBe('string');
    });
});

describe('ModelTransformer with TrackingEvent model that has a helper method colliding with an old-style accessor', function () {
    test('falls back to old-style accessor when the camelCase method is not an Attribute accessor', function () {
        $data = (new ModelTransformer(TrackingEvent::class))->data();

        expect($data['mutators'])
            ->toHaveKey('changes')
            ->and($data['mutators']['changes'])->toBe('{ attributes: Record<string, unknown>; old: Record<string, unknown> }');
    });
});

describe('ModelTransformer with User model respecting relationship_case config', function () {
    test('respects relationship_case config for relation names', function () {
        config()->set('ts-publish.relationship_case', 'snake');

        $data = (new ModelTransformer(User::class))->data();

        expect($data['relations'])->toHaveKey('owned_teams');
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

describe('ModelTransformer modular resolvedImports', function () {
    test('computes modular resolvedImports with relative paths for Invoice model', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Invoice::class))->data();

        // Invoice is in accounting/models
        // InvoiceStatus enum is in accounting/enums → ../enums
        // User model is in app/models → ../../app/models
        // Payment model is in accounting/models → . (same dir)
        expect($data['resolvedImports'])->toHaveKey('../enums');
        expect($data['resolvedImports']['../enums'])->toContain('InvoiceStatusType');

        expect($data['resolvedImports'])->toHaveKey('../../app/models');
        expect($data['resolvedImports']['../../app/models'])->toContain('User');

        expect($data['resolvedImports'])->toHaveKey('.');
        expect($data['resolvedImports']['.'])->toContain('Payment');
    });

    test('computes modular resolvedImports for User model with enum and model imports', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(User::class))->data();

        // User is in app/models
        // Role, MembershipLevel enums are in app/enums → ../enums
        expect($data['resolvedImports'])->toHaveKey('../enums');
        expect($data['resolvedImports']['../enums'])->toContain('RoleType')
            ->and($data['resolvedImports']['../enums'])->toContain('MembershipLevelType');

        // Related models in the same namespace (Profile, Post, etc.) → . (same dir)
        expect($data['resolvedImports'])->toHaveKey('.');
        expect($data['resolvedImports']['.'])->toContain('Profile')
            ->and($data['resolvedImports']['.'])->toContain('Post')
            ->and($data['resolvedImports']['.'])->not->toContain('User');
    });

    test('non-modular resolvedImports uses flat paths', function () {
        config()->set('ts-publish.modular_publishing', false);

        $data = (new ModelTransformer(User::class))->data();

        // Non-modular uses hardcoded '../enums' and './' paths
        expect($data['resolvedImports'])->toHaveKey('../enums');
        expect($data['resolvedImports'])->toHaveKey('./');
    });
});

describe('ModelTransformer import alias resolution for duplicate names', function () {
    test('aliases model imports when two relations reference different models with the same class name', function () {
        // Deal relates to Crm\User (via customer) and App\User (via admin)
        $data = (new ModelTransformer(Deal::class))->data();

        // Both relations should be present with aliased type names
        expect($data['relations'])->toHaveKey('customer')
            ->and($data['relations'])->toHaveKey('admin');

        // Types should use relationship-based aliases since each FQCN has exactly one relation
        expect($data['relations']['customer'])->toBe('CustomerUser');
        expect($data['relations']['admin'])->toBe('AdminUser');

        // Imports should use "OriginalName as Alias" syntax
        $allImports = array_merge(...array_values($data['resolvedImports']));
        expect($allImports)->toContain('User as CustomerUser')
            ->and($allImports)->toContain('User as AdminUser');
    });

    test('aliases enum imports when two enums from different namespaces share the same TypeScript type name', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        // Deal casts status to App\Enums\Status (→ StatusType)
        // and crm_status to Crm\Enums\Status (→ StatusType) — a genuine collision
        $data = (new ModelTransformer(Deal::class))->data();

        // Both columns should use namespace-prefixed aliases
        expect($data['columns']['status'])->toBe('AppStatusType');
        expect($data['columns']['crm_status'])->toBe('CrmStatusType');

        // Imports should use "as" aliasing syntax for the enum types
        $allImports = array_merge(...array_values($data['resolvedImports']));
        expect($allImports)->toContain('StatusType as AppStatusType')
            ->and($allImports)->toContain('StatusType as CrmStatusType');
    });

    test('aliases model imports in flat (non-modular) mode', function () {
        config()->set('ts-publish.modular_publishing', false);

        $data = (new ModelTransformer(Deal::class))->data();

        // Flat mode should still alias conflicting names
        expect($data['relations']['customer'])->toBe('CustomerUser');
        expect($data['relations']['admin'])->toBe('AdminUser');

        expect($data['resolvedImports'])->toHaveKey('./');
        expect($data['resolvedImports']['./'])->toContain('User as AdminUser')
            ->and($data['resolvedImports']['./'])->toContain('User as CustomerUser');
    });

    test('aliases model imports in modular mode with correct relative paths', function () {
        config()->set('ts-publish.modular_publishing', true);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ModelTransformer(Deal::class))->data();

        // Deal is in crm/models
        // Crm\User is in crm/models → . (same dir)
        // App\User is in app/models → ../../app/models
        expect($data['relations']['customer'])->toBe('CustomerUser');
        expect($data['relations']['admin'])->toBe('AdminUser');

        expect($data['resolvedImports'])->toHaveKey('.');
        expect($data['resolvedImports']['.'])->toContain('User as CustomerUser');

        expect($data['resolvedImports'])->toHaveKey('../../app/models');
        expect($data['resolvedImports']['../../app/models'])->toContain('User as AdminUser');
    });

    test('does not alias imports when there are no naming conflicts', function () {
        // Invoice has unique model names (User, Payment) — no conflicts
        $data = (new ModelTransformer(Invoice::class))->data();

        expect($data['relations']['user'])->toBe('User');
        expect($data['relations']['payments'])->toBe('Payment[]');

        // No "as" aliasing in imports
        $allImports = array_merge(...array_values($data['resolvedImports']));

        foreach ($allImports as $importEntry) {
            expect($importEntry)->not->toContain(' as ');
        }
    });

    test('does not alias imports when model name does not conflict with self', function () {
        // User model imports Profile, Post, etc. — none named "User"
        $data = (new ModelTransformer(User::class))->data();

        $allImports = array_merge(...array_values($data['resolvedImports']));

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

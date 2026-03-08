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

    test('transforms User model with TsCasts overrides on casts method', function () {
        $data = (new ModelTransformer(User::class))->data();

        // TsCasts applied on the casts() method override the default type inference
        expect($data['columns']['settings'])->toBe('{ theme: "light" | "dark"; notifications: boolean; locale: string } | null');
        expect($data['columns']['options'])->toBe('Record<string, unknown> | null');
    });

    test('transforms User model enum imports', function () {
        $data = (new ModelTransformer(User::class))->data();

        expect($data['enumImports'])->toContain('RoleType')
            ->and($data['enumImports'])->toContain('MembershipLevelType');
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
        expect($data['modelImports'])->toContain('Profile')
            ->and($data['modelImports'])->toContain('Post')
            ->and($data['modelImports'])->toContain('Image')
            ->and($data['modelImports'])->not->toContain('User');
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

        expect($data['customImports'])->toHaveKey('@js/types/product');

        // Should extract just the importable type names, not primitives or null
        $importedTypes = $data['customImports']['@js/types/product'];
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

        expect($data['enumImports'])
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
        expect($data['modelImports'])->not->toContain('Category');
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

        expect($data['enumImports'])->toContain('InvoiceStatusType');

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

        expect($data['enumImports'])
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

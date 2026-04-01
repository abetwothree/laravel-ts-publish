<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Currency;
use Workbench\App\Enums\MembershipLevel;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

describe('EnumResource with backed int enum', function () {
    it('transforms the last case of a backed int enum', function () {
        $result = (new EnumResource(Status::Published))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'Published')
            ->toHaveKey('value', 1)
            ->toHaveKey('backed', true)
            ->toHaveKey('icon', 'check')
            ->toHaveKey('color', 'green');
    });

    it('resolves instance methods for a non-last case', function () {
        $result = (new EnumResource(Status::Draft))->toArray(request());

        expect($result)
            ->toHaveKey('name', 'Draft')
            ->toHaveKey('value', 0)
            ->toHaveKey('backed', true)
            ->toHaveKey('icon', 'pencil')
            ->toHaveKey('color', 'gray');
    });

    it('includes static method return values', function () {
        $result = (new EnumResource(Status::Published))->toArray(request());

        expect($result)
            ->toHaveKey('valueLabelPair')
            ->toHaveKey('names', ['Draft', 'Published'])
            ->toHaveKey('values', [0, 1])
            ->toHaveKey('options', ['Draft' => 0, 'Published' => 1]);
    });
});

describe('EnumResource with unit enum', function () {
    it('transforms a unit enum with no published methods', function () {
        $result = (new EnumResource(Role::Admin))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'Admin')
            ->toHaveKey('value', 'Admin')
            ->toHaveKey('backed', false)
            ->not->toHaveKey('canManageUsers')
            ->not->toHaveKey('privilegedRoles');
    });

    it('uses the case name as value for unit enums', function () {
        $result = (new EnumResource(Role::Guest))->toArray(request());

        expect($result['value'])->toBe('Guest');
    });
});

describe('EnumResource with backed string enum', function () {
    it('transforms a string-backed enum with instance methods', function () {
        $result = (new EnumResource(Color::Red))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'Red')
            ->toHaveKey('value', 'red')
            ->toHaveKey('backed', true)
            ->toHaveKey('hex', '#EF4444')
            ->toHaveKey('rgb', [239, 68, 68]);
    });

    it('resolves correct method values for middle cases', function () {
        $result = (new EnumResource(Color::Blue))->toArray(request());

        expect($result)
            ->toHaveKey('hex', '#3B82F6')
            ->toHaveKey('rgb', [59, 130, 246]);
    });

    it('transforms a string-backed enum with only static methods', function () {
        $result = (new EnumResource(Currency::Usd))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'Usd')
            ->toHaveKey('value', 'USD')
            ->toHaveKey('backed', true)
            ->toHaveKey('symbols')
            ->toHaveKey('default', 'USD')
            ->toHaveKey('details');
    });
});

describe('EnumResource with parameterized methods', function () {
    it('includes methods with params provided via attribute', function () {
        $result = (new EnumResource(Priority::High))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'High')
            ->toHaveKey('value', 2)
            ->toHaveKey('backed', true)
            ->toHaveKey('label', 'High Priority')
            ->toHaveKey('badgeColor', 'bg-orange-100 text-orange-800')
            ->toHaveKey('icon', 'arrow-up')
            ->toHaveKey('isAboveThreshold', true)
            ->toHaveKey('filterByMinimum');
    });

    it('excludes methods with required params but no attribute params', function () {
        $result = (new EnumResource(Priority::High))->toArray(request());

        expect($result)
            ->not->toHaveKey('isAboveCeiling')
            ->not->toHaveKey('filterByMaximum');
    });

    it('excludes unpublished methods', function () {
        $result = (new EnumResource(Priority::High))->toArray(request());

        expect($result)
            ->not->toHaveKey('numericWeight')
            ->not->toHaveKey('highestValue');
    });
});

describe('EnumResource with minimal enum', function () {
    it('transforms a unit enum with no attributes', function () {
        $result = (new EnumResource(MembershipLevel::Premium))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'Premium')
            ->toHaveKey('value', 'Premium')
            ->toHaveKey('backed', false);

        expect($result)->toHaveCount(3);
    });
});

describe('EnumResource with TsCase overrides', function () {
    it('returns the overridden name and value from TsCase', function () {
        $result = (new EnumResource(Color::Amber))->toArray(request());

        expect($result)
            ->toBeArray()
            ->toHaveKey('name', 'Yellow')
            ->toHaveKey('value', 'yellow')
            ->toHaveKey('backed', true)
            ->toHaveKey('hex', '#F59E0B')
            ->toHaveKey('rgb', [245, 158, 11]);
    });

    it('returns the overridden name with original value when only name is overridden', function () {
        $result = (new EnumResource(Color::Gray))->toArray(request());

        expect($result)
            ->toHaveKey('name', 'Slate')
            ->toHaveKey('value', 'slate');
    });

    it('returns original name and value when no TsCase override exists', function () {
        $result = (new EnumResource(Color::Purple))->toArray(request());

        expect($result)
            ->toHaveKey('name', 'Purple')
            ->toHaveKey('value', 'purple');
    });
});

describe('EnumResource with null resource', function () {
    it('returns null when the resource is null', function () {
        $result = (new EnumResource(null))->toArray(request());

        expect($result)->toBeNull();
    });
});

describe('Integration of EnumResource in PostResource class', function () {
    it('transforms all enum properties into instanced enum format', function () {
        $post = new Post([
            'id' => 1,
            'title' => 'Test Post',
            'content' => 'Test content',
            'user_id' => 1,
            'status' => Status::Published,
            'visibility' => Visibility::Public,
            'priority' => Priority::High,
        ]);

        $result = (new PostResource($post))->response()->getData(true)['data'];

        expect($result['status'])
            ->toBeArray()
            ->toHaveKey('name', 'Published')
            ->toHaveKey('value', 1)
            ->toHaveKey('backed', true)
            ->toHaveKey('icon', 'check')
            ->toHaveKey('color', 'green');

        expect($result['visibility'])
            ->toBeArray()
            ->toHaveKey('name', 'Public')
            ->toHaveKey('value', 'Public')
            ->toHaveKey('backed', false)
            ->toHaveKey('isPublic', true)
            ->toHaveKey('description', 'Visible to everyone');

        expect($result['priority'])
            ->toBeArray()
            ->toHaveKey('name', 'High')
            ->toHaveKey('value', 2)
            ->toHaveKey('backed', true)
            ->toHaveKey('label', 'High Priority')
            ->toHaveKey('icon', 'arrow-up');
    });

    it('returns null for nullable enum properties when they are null', function () {
        $post = new Post([
            'id' => 2,
            'title' => 'Minimal Post',
            'content' => 'Content',
            'user_id' => 1,
            'status' => Status::Draft,
            'visibility' => null,
            'priority' => null,
        ]);

        $result = (new PostResource($post))->response()->getData(true)['data'];

        expect($result['status'])
            ->toBeArray()
            ->toHaveKey('name', 'Draft')
            ->toHaveKey('value', 0)
            ->toHaveKey('backed', true);

        expect($result['visibility'])->toBeNull();
        expect($result['priority'])->toBeNull();
    });

    it('handles mixed null and non-null enum properties', function () {
        $post = new Post([
            'id' => 3,
            'title' => 'Partial Post',
            'content' => 'Content',
            'user_id' => 1,
            'status' => Status::Published,
            'visibility' => Visibility::Private,
            'priority' => null,
        ]);

        $result = (new PostResource($post))->response()->getData(true)['data'];

        expect($result['status'])
            ->toBeArray()
            ->toHaveKey('name', 'Published');

        expect($result['visibility'])
            ->toBeArray()
            ->toHaveKey('name', 'Private')
            ->toHaveKey('backed', false)
            ->toHaveKey('isPublic', false)
            ->toHaveKey('description', 'Only visible to the owner');

        expect($result['priority'])->toBeNull();
    });
});

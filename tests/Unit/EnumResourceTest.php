<?php

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Currency;
use Workbench\App\Enums\MembershipLevel;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

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
            ->toHaveKey('backed', true);
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
            ->toHaveKey('isAboveThreshold', true);
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

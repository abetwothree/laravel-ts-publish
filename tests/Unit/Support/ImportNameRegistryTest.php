<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\RecordingImportNameRegistry;

describe('ImportNameRegistry', function () {
    test('non-colliding names pass through unaliased', function () {
        $registry = new ImportNameRegistry;
        $registry->register('App\Models\User', 'User');
        $registry->register('App\Models\Team', 'Team');

        expect($registry->resolve())->toBe([
            'App\Models\User' => 'User',
            'App\Models\Team' => 'Team',
        ]);
    });

    test('same basename from different namespaces gets namespace-prefixed aliases', function () {
        $registry = new ImportNameRegistry;
        $registry->register('App\Sales\Models\Report', 'Report');
        $registry->register('App\Marketing\Models\Report', 'Report');

        expect($registry->resolve())->toBe([
            'App\Sales\Models\Report' => 'SalesReport',
            'App\Marketing\Models\Report' => 'MarketingReport',
        ]);
    });

    test('colliding one-segment prefixes extend until unique (same basename, same parent segment)', function () {
        $registry = new ImportNameRegistry;
        $registry->register('Acme\Customer\Billing\Rate\Models\Rate', 'Rate');
        $registry->register('Acme\Billing\Rate\Models\Rate', 'Rate');

        $resolved = $registry->resolve();

        // Depth 1 ('RateRate') and depth 2 ('BillingRateRate')
        // collide for BOTH; the whole group advances to depth 3 together, so neither
        // keeps an ambiguous shallow alias.
        expect($resolved['Acme\Customer\Billing\Rate\Models\Rate'])
            ->toBe('CustomerBillingRateRate')
            ->and($resolved['Acme\Billing\Rate\Models\Rate'])
            ->toBe('AcmeBillingRateRate')
            ->and(array_unique(array_values($resolved)))->toHaveCount(2);
        // A namespace_strip_prefix of 'Acme' drops that segment, which yields BillingRateRate for the second model.
    });

    test('identical namespaces fall back to numeric suffixes', function () {
        $registry = new ImportNameRegistry;
        $registry->register('A\B\Thing', 'Thing');
        $registry->register('A\B\ThingAlias', 'Thing'); // same TS name, same prefix path

        $resolved = $registry->resolve();

        expect(array_unique(array_values($resolved)))->toHaveCount(2);
    });

    test('a reserved name forces the import to alias', function () {
        $registry = new ImportNameRegistry;
        $registry->reserve('Order');
        $registry->register('App\Models\Order', 'Order');

        expect($registry->resolve()['App\Models\Order'])->toBe('ModelsOrder');
    });

    test('several names can be reserved at once, as when a const registry learns the names a type registry took', function () {
        $types = new ImportNameRegistry;
        $types->register('App\Enums\Role', 'RoleType');
        $types->register('App\Models\Grade', 'Grade');

        $consts = new ImportNameRegistry;
        $consts->reserveMany(...array_values($types->resolve()));
        $consts->register('Crm\Enums\RoleType', 'RoleType');
        $consts->register('App\Enums\Grade', 'Grade');
        $consts->register('App\Enums\Role', 'Role');

        expect($consts->resolve())->toBe([
            'Crm\Enums\RoleType' => 'CrmRoleType',
            'App\Enums\Grade' => 'EnumsGrade',
            'App\Enums\Role' => 'Role',
        ]);
    });

    test('a registry overriding reserve() with its one parameter loads, and reserveMany() reserves through it', function () {
        $registry = new RecordingImportNameRegistry;
        $registry->reserveMany('Order', 'Grade');
        $registry->register('App\Models\Order', 'Order');

        expect($registry->reservations)->toBe(['Order', 'Grade'])
            ->and($registry->resolve())->toBe(['App\Models\Order' => 'ModelsOrder']);
    });

    test('preferred alias wins when unique and falls back when it collides', function () {
        $registry = new ImportNameRegistry;
        $registry->register('App\Models\A\User', 'User', preferredAlias: 'OwnerUser');
        $registry->register('App\Models\B\User', 'User', preferredAlias: 'OwnerUser');

        $resolved = $registry->resolve();

        expect(array_unique(array_values($resolved)))->toHaveCount(2)
            ->and($resolved)->not->toContain('OwnerUser');
        // Both preferred aliases collide, so both fall back to namespace prefixes.
    });

    test('an alias never collides with an unaliased import name', function () {
        $registry = new ImportNameRegistry;
        $registry->register('App\Models\SalesReport', 'SalesReport');
        $registry->register('App\Sales\Models\Report', 'Report');
        $registry->register('App\Marketing\Models\Report', 'Report');

        $resolved = $registry->resolve();

        // Sales\Report's depth-1 alias would be 'SalesReport' — taken by a real import.
        expect(array_unique(array_values($resolved)))->toHaveCount(3);
    });

    test('a given FQCN gets the same alias regardless of registration order', function () {
        $a = new ImportNameRegistry;
        $a->register('Acme\Customer\Billing\Rate\Models\Rate', 'Rate');
        $a->register('Acme\Billing\Rate\Models\Rate', 'Rate');

        $b = new ImportNameRegistry;
        $b->register('Acme\Billing\Rate\Models\Rate', 'Rate');
        $b->register('Acme\Customer\Billing\Rate\Models\Rate', 'Rate');

        foreach ($a->resolve() as $fqcn => $alias) {
            expect($b->resolve()[$fqcn])->toBe($alias);
        }
    });

    test('two different colliding type-name groups resolve identically regardless of which group registers first', function () {
        $a = new ImportNameRegistry;
        $a->register('Org\Sales\Alpha', 'Alpha');
        $a->register('Org\Marketing\Alpha', 'Alpha');
        $a->register('Org\X\Beta', 'Beta', preferredAlias: 'SalesAlpha'); // collides with the Alpha group's natural alias
        $a->register('Org\Y\Beta', 'Beta', preferredAlias: 'ZetaBeta');

        $b = new ImportNameRegistry;
        $b->register('Org\X\Beta', 'Beta', preferredAlias: 'SalesAlpha');
        $b->register('Org\Y\Beta', 'Beta', preferredAlias: 'ZetaBeta');
        $b->register('Org\Sales\Alpha', 'Alpha');
        $b->register('Org\Marketing\Alpha', 'Alpha');

        $resolvedA = $a->resolve();
        $resolvedB = $b->resolve();

        // Whichever of the Alpha/Beta groups resolve() processes first claims 'SalesAlpha'.
        // If group-processing order tracked registration order instead of being independent
        // of it, $a and $b (which register the two groups in opposite order) would disagree.
        foreach ($resolvedA as $fqcn => $alias) {
            expect($resolvedB[$fqcn])->toBe($alias);
        }

        expect(array_unique(array_values($resolvedA)))->toHaveCount(4);
    });
});

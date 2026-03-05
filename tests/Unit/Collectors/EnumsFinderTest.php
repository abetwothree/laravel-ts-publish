<?php

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use Illuminate\Support\Collection;

use function Orchestra\Testbench\workbench_path;

test('enums collector works correctly', function () {
    $collector = resolve(EnumsCollector::class);

    $enums = $collector->collect();

    expect($enums)
        ->toBeInstanceOf(Collection::class);
});

test('enums collector includes only classes from a directory', function () {
    config()->set('ts-publish.included_enums', [
        workbench_path('modules/Accounting/Enums'),
    ]);

    $enums = resolve(EnumsCollector::class)->collect();

    expect($enums)
        ->toHaveCount(2)
        ->toContain('Workbench\Accounting\Enums\PaymentStatus')
        ->toContain('Workbench\Accounting\Enums\InvoiceStatus');
});

test('enums collector includes a mix of class names and directories', function () {
    config()->set('ts-publish.included_enums', [
        'Workbench\App\Enums\Status',
        workbench_path('modules/Accounting/Enums'),
    ]);

    $enums = resolve(EnumsCollector::class)->collect();

    expect($enums)
        ->toHaveCount(3)
        ->toContain('Workbench\App\Enums\Status')
        ->toContain('Workbench\Accounting\Enums\PaymentStatus')
        ->toContain('Workbench\Accounting\Enums\InvoiceStatus');
});

test('enums collector excludes classes from a directory', function () {
    config()->set('ts-publish.excluded_enums', [
        workbench_path('app/Enums'),
    ]);

    $enums = resolve(EnumsCollector::class)->collect();

    // InvoiceStatus, PaymentStatus, and Shipping\Status remain because they are in additional_enum_directories
    expect($enums)
        ->toHaveCount(3)
        ->toContain('Workbench\Accounting\Enums\InvoiceStatus')
        ->toContain('Workbench\Accounting\Enums\PaymentStatus')
        ->toContain('Workbench\Shipping\Enums\Status');
});

test('enums collector excludes a mix of class names and directories', function () {
    config()->set('ts-publish.additional_enum_directories', [
        workbench_path('modules/Accounting/Enums'),
    ]);
    config()->set('ts-publish.excluded_enums', [
        'Workbench\App\Enums\Status',
        workbench_path('modules/Accounting/Enums'),
    ]);

    $enums = resolve(EnumsCollector::class)->collect();

    expect($enums)
        ->not->toContain('Workbench\App\Enums\Status')
        ->not->toContain('Workbench\Accounting\Enums\PaymentStatus')
        ->not->toContain('Workbench\Accounting\Enums\InvoiceStatus');
});

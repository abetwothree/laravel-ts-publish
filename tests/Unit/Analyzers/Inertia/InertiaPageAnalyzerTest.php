<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use Laravel\Ranger\Collectors\Response as ResponseCollector;
use Laravel\Ranger\Components\JsonResponse;

// ─── componentToFqn() ─────────────────────────────────────────────

test('converts simple component name to FQN', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('Dashboard'))
        ->toBe('Inertia.Pages.Dashboard');
});

test('converts slash-separated component to dot-separated FQN', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('Settings/General'))
        ->toBe('Inertia.Pages.Settings.General');
});

test('converts kebab-case component segments to StudlyCase', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('settings/two-factor'))
        ->toBe('Inertia.Pages.Settings.TwoFactor');
});

test('converts double-colon separator to dots', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->componentToFqn('Admin::Dashboard'))
        ->toBe('Inertia.Pages.Admin.Dashboard');
});

// ─── analyze() with mocked collector ─────────────────────────────

test('returns null when collector returns empty array', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $mock->shouldReceive('parseResponse')->andReturn([]);

    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->analyze(['uses' => 'Foo@bar']))->toBeNull();
});

test('returns null when collector returns only non-string responses', function () {
    $mock = Mockery::mock(ResponseCollector::class);
    $mock->shouldReceive('parseResponse')->andReturn([
        new JsonResponse(['key' => 'value']),
    ]);

    $analyzer = new InertiaPageAnalyzer($mock);

    expect($analyzer->analyze(['uses' => 'Foo@bar']))->toBeNull();
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\PackageJson;
use Illuminate\Support\Facades\File;

function fakePackageJson(array $payload): void
{
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn((string) json_encode($payload));
}

it('returns the first installed candidate from dependencies', function () {
    fakePackageJson(['dependencies' => ['@inertiaui/table-vue' => '^4.0']]);

    expect(PackageJson::firstInstalled(['@inertiaui/table-vue', '@inertiaui/table-react']))
        ->toBe('@inertiaui/table-vue');
});

it('detects packages declared in devDependencies', function () {
    fakePackageJson(['devDependencies' => ['@inertiaui/table-react' => '^4.0']]);

    expect(PackageJson::firstInstalled(['@inertiaui/table-vue', '@inertiaui/table-react']))
        ->toBe('@inertiaui/table-react');
});

it('respects candidate order over package.json order', function () {
    fakePackageJson(['dependencies' => [
        '@inertiaui/table-react' => '^4.0',
        '@inertiaui/table-vue' => '^4.0',
    ]]);

    expect(PackageJson::firstInstalled(['@inertiaui/table-vue', '@inertiaui/table-react']))
        ->toBe('@inertiaui/table-vue');
});

it('returns null when no candidate is installed', function () {
    fakePackageJson(['dependencies' => ['vue' => '^3.0']]);

    expect(PackageJson::firstInstalled(['@inertiaui/table-vue', '@inertiaui/table-react']))
        ->toBeNull();
});

it('returns null when package.json is missing', function () {
    File::shouldReceive('exists')->andReturn(false);

    expect(PackageJson::firstInstalled(['@inertiaui/table-vue']))->toBeNull();
});

it('reports presence with has()', function () {
    fakePackageJson(['dependencies' => ['@laravel/echo-vue' => '^2.0']]);

    expect(PackageJson::has('@laravel/echo-vue'))->toBeTrue()
        ->and(PackageJson::has('@laravel/echo-react'))->toBeFalse();
});

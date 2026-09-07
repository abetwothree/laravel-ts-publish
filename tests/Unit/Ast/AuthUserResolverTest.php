<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AuthUserResolver;
use Workbench\App\Models\User;

it('resolves the default guard provider model', function () {
    expect((new AuthUserResolver)->model())->toBe(User::class);
});

it('returns null when the default guard is missing', function () {
    config()->set('auth.defaults.guard', null);

    expect((new AuthUserResolver)->model())->toBeNull();
});

it('returns null when the guard has no provider', function () {
    config()->set('auth.guards.web.provider', null);

    expect((new AuthUserResolver)->model())->toBeNull();
});

it('returns null when the provider is not Eloquent-backed', function () {
    config()->set('auth.guards.web.provider', 'legacy');
    config()->set('auth.providers.legacy', ['driver' => 'database', 'table' => 'users']);

    expect((new AuthUserResolver)->model())->toBeNull();
});

it('returns null when the provider model is not a Model subclass', function () {
    config()->set('auth.providers.users.model', stdClass::class);

    expect((new AuthUserResolver)->model())->toBeNull();
});

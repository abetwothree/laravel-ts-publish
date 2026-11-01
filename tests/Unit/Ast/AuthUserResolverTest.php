<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NameLengthMember;
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

it('names the default guard model as the sole one while no other guard names another Eloquent model', function () {
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    config()->set('auth.guards.api', ['driver' => 'token', 'provider' => null]);
    config()->set('auth.guards.legacy', ['driver' => 'session', 'provider' => 'legacy']);
    config()->set('auth.providers.legacy', ['driver' => 'database', 'table' => 'users']);

    expect((new AuthUserResolver)->soleModel())->toBe(User::class);
});

it('names no sole model where another guard\'s provider names another Eloquent model', function () {
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'members']);
    config()->set('auth.providers.members', ['driver' => 'eloquent', 'model' => NameLengthMember::class]);

    expect((new AuthUserResolver)->soleModel())->toBeNull()
        ->and((new AuthUserResolver)->model())->toBe(User::class);
});

it('names no sole model where the default guard names no Eloquent model', function () {
    config()->set('auth.providers.users.model', stdClass::class);

    expect((new AuthUserResolver)->soleModel())->toBeNull();
});

it('counts a provider model spelled with a leading backslash or through a class alias as the model it loads', function () {
    if (! class_exists('AuthUserResolverTestUserAlias', false)) {
        class_alias(User::class, 'AuthUserResolverTestUserAlias');
    }

    config()->set('auth.guards.slashed', ['driver' => 'session', 'provider' => 'slashed']);
    config()->set('auth.providers.slashed', ['driver' => 'eloquent', 'model' => '\\'.User::class]);
    config()->set('auth.guards.aliased', ['driver' => 'session', 'provider' => 'aliased']);
    config()->set('auth.providers.aliased', ['driver' => 'eloquent', 'model' => 'AuthUserResolverTestUserAlias']);

    expect((new AuthUserResolver)->soleModel())->toBe(User::class);
});

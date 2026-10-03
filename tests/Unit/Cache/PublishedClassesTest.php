<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\OpaqueHandle;

it('cannot judge a name that is no class, so it leaves the name alone', function () {
    expect(PublishedClasses::exports('Totally\Made\Up\Class'))->toBeTrue();
});

it('counts every model and resource as exported while it has no published set to read', function () {
    expect(PublishedClasses::exports(User::class))->toBeTrue()
        ->and(PublishedClasses::exports(UserResource::class))->toBeTrue();
});

it('never counts a class that is no model, resource or enum: no file is generated for one', function () {
    expect(PublishedClasses::exports(OpaqueHandle::class))->toBeFalse();
});

it('leaves an enum alone, whatever the published sets hold: its name travels on its own channel', function () {
    PublishedModelRegistry::register([User::class]);
    PublishedResourceRegistry::register([TeamResource::class]);

    expect(PublishedClasses::exports(Status::class))->toBeTrue();
});

it('narrows a model to the published model set', function () {
    PublishedModelRegistry::register([User::class]);

    expect(PublishedClasses::exports(User::class))->toBeTrue()
        ->and(PublishedClasses::exports(Post::class))->toBeFalse()
        // The model set says nothing about resources.
        ->and(PublishedClasses::exports(UserResource::class))->toBeTrue();
});

it('narrows a resource to the published resource set', function () {
    PublishedResourceRegistry::register([TeamResource::class]);

    expect(PublishedClasses::exports(TeamResource::class))->toBeTrue()
        ->and(PublishedClasses::exports(UserResource::class))->toBeFalse()
        ->and(PublishedClasses::exports(User::class))->toBeTrue();
});

it('reads a name with a leading backslash as the class it spells', function () {
    PublishedModelRegistry::register([User::class]);
    PublishedResourceRegistry::register([TeamResource::class]);

    expect(PublishedClasses::exports('\\'.User::class))->toBeTrue()
        ->and(PublishedClasses::exports('\\'.Post::class))->toBeFalse()
        ->and(PublishedClasses::exports('\\'.TeamResource::class))->toBeTrue()
        ->and(PublishedClasses::exports('\\'.UserResource::class))->toBeFalse();
});

it('reads a name in any letter case as the class it spells', function () {
    // The autoloader is case-sensitive, so a lower-cased name finds only a class already loaded under its own name.
    foreach ([User::class, Post::class, TeamResource::class, UserResource::class] as $class) {
        class_exists($class);
    }

    PublishedModelRegistry::register([User::class]);
    PublishedResourceRegistry::register([TeamResource::class]);

    expect(PublishedClasses::exports(strtolower(User::class)))->toBeTrue()
        ->and(PublishedClasses::exports(strtolower(Post::class)))->toBeFalse()
        ->and(PublishedClasses::exports(strtolower(TeamResource::class)))->toBeTrue()
        ->and(PublishedClasses::exports(strtolower(UserResource::class)))->toBeFalse();
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

afterEach(fn () => PublishedModelRegistry::reset());

it('starts empty, so it reports no information', function () {
    expect(PublishedModelRegistry::isEmpty())->toBeTrue()
        ->and(PublishedModelRegistry::signature())->toBe('');
});

it('fails open while empty: every class counts as published', function () {
    expect(PublishedModelRegistry::isPublished(User::class))->toBeTrue()
        ->and(PublishedModelRegistry::isPublished('Totally\Made\Up\Class'))->toBeTrue();
});

it('narrows to exactly the registered classes once populated', function () {
    PublishedModelRegistry::register([User::class]);

    expect(PublishedModelRegistry::isEmpty())->toBeFalse()
        ->and(PublishedModelRegistry::isPublished(User::class))->toBeTrue()
        ->and(PublishedModelRegistry::isPublished(Post::class))->toBeFalse();
});

it('accumulates across register() calls', function () {
    PublishedModelRegistry::register([User::class]);
    PublishedModelRegistry::register([Post::class]);

    expect(PublishedModelRegistry::isPublished(User::class))->toBeTrue()
        ->and(PublishedModelRegistry::isPublished(Post::class))->toBeTrue();
});

it('ignores a leading backslash, on a name it is given and on one it is asked about', function () {
    PublishedModelRegistry::register([User::class]);
    PublishedModelRegistry::register(['\\'.Post::class]);

    expect(PublishedModelRegistry::isPublished('\\'.User::class))->toBeTrue()
        ->and(PublishedModelRegistry::isPublished(Post::class))->toBeTrue();
});

it('returns to the no-information state on reset()', function () {
    PublishedModelRegistry::register([User::class]);
    $memoized = PublishedModelRegistry::signature();
    PublishedModelRegistry::reset();

    expect($memoized)->not->toBe('')
        ->and(PublishedModelRegistry::isEmpty())->toBeTrue()
        ->and(PublishedModelRegistry::isPublished(Post::class))->toBeTrue()
        ->and(PublishedModelRegistry::signature())->toBe('');
});

it('moves its version on every change, so an answer read against an older set is never reused', function () {
    $start = PublishedModelRegistry::version();

    PublishedModelRegistry::register([User::class]);
    $registered = PublishedModelRegistry::version();

    PublishedModelRegistry::reset();

    expect($registered)->toBeGreaterThan($start)
        ->and(PublishedModelRegistry::version())->toBeGreaterThan($registered);
});

it('signs the set, not the order it was registered in', function () {
    PublishedModelRegistry::register([User::class, Post::class]);
    $signature = PublishedModelRegistry::signature();
    $again = PublishedModelRegistry::signature();

    PublishedModelRegistry::reset();
    PublishedModelRegistry::register([Post::class]);
    $partial = PublishedModelRegistry::signature();
    PublishedModelRegistry::register([User::class]);

    expect($signature)->not->toBe('')
        ->and($again)->toBe($signature)
        ->and($partial)->not->toBe($signature)
        ->and(PublishedModelRegistry::signature())->toBe($signature);
});

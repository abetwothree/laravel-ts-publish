<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

it('says whether the runtime class is known, and keys the memo as the scope did', function () {
    $post = new ReflectionClass(Post::class);

    expect(RuntimeClass::known($post)->knownClass())->toBe($post)
        ->and(RuntimeClass::assumed($post)->knownClass())->toBeNull()
        ->and(RuntimeClass::known($post)->memoKey(PostResource::class))->toBe('@runtime:'.Post::class)
        ->and(RuntimeClass::assumed($post)->memoKey(PostResource::class))->toBe('@runtime:'.Post::class.'@assumed');
});

it('keys a runtime class apart from the class read only where it differs, and an assumed one apart from a known one', function () {
    $resource = new ReflectionClass(PostResource::class);

    expect([
        RuntimeClass::known($resource)->memoKey(PostResource::class),
        RuntimeClass::assumed($resource)->memoKey(PostResource::class),
    ])->toBe(['', '@assumed']);
});

it('carries whether it is known onto another class', function () {
    $post = new ReflectionClass(Post::class);
    $resource = new ReflectionClass(PostResource::class);

    expect(RuntimeClass::known($resource)->withClass($post)->knownClass())->toBe($post)
        ->and(RuntimeClass::assumed($resource)->withClass($post))
        ->class->toBe($post)
        ->known->toBeFalse();
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

it('says whether the runtime class is known, and keys the memo as the scope did', function () {
    $post = new ReflectionClass(Post::class);

    expect(RuntimeClass::known($post)->knownClass())->toBe($post)
        ->and(RuntimeClass::assumed($post)->knownClass())->toBeNull()
        ->and(RuntimeClass::known($post)->memoKey(PostResource::class))->toBe(AnalysisMemo::runtimeKey(PostResource::class, $post, false))
        ->and(RuntimeClass::assumed($post)->memoKey(PostResource::class))->toBe(AnalysisMemo::runtimeKey(PostResource::class, $post, true));
});

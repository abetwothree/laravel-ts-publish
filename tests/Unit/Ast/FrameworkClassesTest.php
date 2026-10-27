<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

it('names a class Laravel declares, written with or without a leading separator, and no application class', function () {
    expect([
        FrameworkClasses::is(JsonResource::class),
        FrameworkClasses::is('\\'.Model::class),
        FrameworkClasses::is(PostResource::class),
        FrameworkClasses::is(Post::class),
    ])->toBe([true, true, false, false]);
});

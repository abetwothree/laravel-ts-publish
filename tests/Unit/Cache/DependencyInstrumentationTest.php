<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;

it('records the wrapped model file as a dependency of a resource', function () {
    DependencyRecorder::start();

    new ResourceTransformer(CommentResource::class);

    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();

    expect($paths)->toContain((new ReflectionClass(Comment::class))->getFileName());
});

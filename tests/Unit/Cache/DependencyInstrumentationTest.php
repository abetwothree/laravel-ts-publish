<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;

it('records the wrapped model file as a dependency of a resource', function () {
    DependencyRecorder::start();

    new ResourceTransformer(CommentResource::class);

    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();

    expect($paths)->toContain((new ReflectionClass(Comment::class))->getFileName());
});

it('records accessor-returned models inlined via only()/except() as dependencies', function () {
    DependencyRecorder::start();

    // Warehouse::lastUserActivityBy() is Attribute<CrmUser|User|null, never>; a
    // resource that does $this->last_user_activity_by->except([...]) inlines these
    // models' columns, so they must be recorded as cache dependencies.
    $fqcns = resolve(ModelAttributeResolver::class)
        ->resolveAccessorModelFqcns(Warehouse::class, 'last_user_activity_by');

    $paths = DependencyRecorder::paths();
    DependencyRecorder::stop();

    expect($fqcns)->not->toBeEmpty()
        ->and($paths)->toContain((new ReflectionClass(User::class))->getFileName());
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use Workbench\App\Http\Resources\CollectionPipelineResource;
use Workbench\App\Models\Post;

test('collection pipelines keep their element types to the end of the chain', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(CollectionPipelineResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['comment_ids']['type'])->toBe('number[]')
        ->and($props['title_words']['type'])->toBe('{ word: string }[]')
        ->and($props['author_name']['type'])->toBe('string | null')
        ->and($props['author_name_or_guest']['type'])->toBe('string | null')
        ->and($props['doubled']['type'])->toBe('Comment[]')
        ->and($props['typed']['type'])->toBe('{ id: number }[]')
        ->and($props['typed']['optional'])->toBeTrue();
});

test('data_get declines a wildcard key', function () {
    $scope = new AnalysisScope(new ReflectionClass(CollectionPipelineResource::class), Post::class);
    $expr = new AstParser()->parseSource('<?php data_get($this->comments, "*.id");')[0]->expr;

    expect(new KnownFunctionCallHandler()->resolve($expr, $scope, new ResourceAstAnalyzer(new ReflectionClass(CollectionPipelineResource::class), Post::class)))->toBeNull();
});

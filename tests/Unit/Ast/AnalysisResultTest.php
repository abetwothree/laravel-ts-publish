<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisImports;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use Workbench\App\Events\PayloadDiffersEvent;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

describe('AstEngine::analyze()', function () {
    test('returns the same properties analyzeMethod() does', function () {
        $engine = resolve(AstEngine::class);

        $result = $engine->analyze(UserResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect($result)->toBeInstanceOf(AnalysisResult::class)
            ->and($result->properties)->not->toBeEmpty()
            ->and($result->properties)->toBe($engine->analyzeMethod(UserResource::class)->properties);
    });

    test('returns the imports AnalysisImports::build() would for the same path', function () {
        $engine = resolve(AstEngine::class);
        $expected = new AnalysisImports()->build($engine->analyzeMethod(UserResource::class), 'workbench/app/http/resources');

        $result = $engine->analyze(UserResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect($result->typeImports)->toBe($expected['typeImports'])
            ->and($result->valueImports)->toBe($expected['valueImports']);
    });

    test('a resource that reads an enum directly carries a type import and no value import', function () {
        // buildValueImports() short-circuits to [] when this is off, which would make the second
        // assertion vacuous rather than a statement about direct enum access.
        config()->set('ts-publish.enums.use_tolki_package', true);

        $result = resolve(AstEngine::class)->analyze(CommentResource::class, 'toArray', null, 'workbench/app/http/resources');

        expect($result->typeImports)->toHaveKey('../../enums')
            ->and($result->valueImports)->toBe([]);
    });

    test('forwards the method and model class to analyzeMethod()', function () {
        $engine = resolve(AstEngine::class);

        expect(array_column($engine->analyze(PayloadDiffersEvent::class, 'broadcastWith')->properties, 'name'))
            ->toBe(['team', 'kind', 'count'])
            ->and($engine->analyze(PayloadDiffersEvent::class)->properties)->toBe([]);

        $resolved = $engine->analyze(UserResource::class, 'toArray', null, 'workbench/app/http/resources');
        $overridden = $engine->analyze(UserResource::class, 'toArray', Post::class, 'workbench/app/http/resources');

        expect(collect($resolved->properties)->firstWhere('name', 'name')['type'])->toBe('string')
            ->and(collect($overridden->properties)->firstWhere('name', 'name')['type'])->toBe('unknown');
    });
});

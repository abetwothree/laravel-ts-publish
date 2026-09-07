<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectMethodTypeResolver;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;

describe('SubjectMethodTypeResolver::resolve()', function () {
    test('declines when nothing in scope declares the method', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        // Comment declares no can(); CommentResource declares no can(); JsonResource declares no can().
        expect(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'can'))->toBeNull();
    });

    test('still answers from the model when it declares the method', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        expect(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'getTable'))->not->toBeNull();
    });
});

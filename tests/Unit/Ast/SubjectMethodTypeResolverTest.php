<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathModelResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;

describe('SubjectMethodTypeResolver::resolve()', function () {
    test('declines when nothing in scope declares the method', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        // Comment declares no can(); CommentResource declares no can(); JsonResource declares no can().
        expect(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'can'))->toBeNull();
    });

    test('a declined own declaration never falls back to the backing model', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
        $shadowing = new AnalysisScope(new ReflectionClass(CallPathModelResource::class), CallPathModel::class);

        // JsonResource's resolveRouteBinding() throws; Comment's would answer Model|null. The model's shadowedLabel()
        // answers string, which PHP never runs from the resource.
        expect(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'resolveRouteBinding'))->toBeNull()
            ->and(resolve(SubjectMethodTypeResolver::class)->resolve($shadowing, 'shadowedLabel'))->toBeNull();
    });

    test('still answers from the model when it declares the method', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        expect(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'getTable'))->not->toBeNull();
    });
});

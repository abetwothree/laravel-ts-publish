<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeInvoiceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
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

    test('the subject\'s own declaration is final, so a model namesake never answers for it', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
        $shadowing = new AnalysisScope(new ReflectionClass(CallPathModelResource::class), CallPathModel::class);
        $invoice = new AnalysisScope(new ReflectionClass(NamesakeInvoiceResource::class), NamesakeInvoice::class);

        // shadowedLabel()'s own body delegates to the model's string namesake. Nothing types opaque(), and JsonResource's
        // resolveRouteBinding() declares nothing, so the model's typed namesake answers for neither.
        expect(resolve(SubjectMethodTypeResolver::class)->resolve($shadowing, 'shadowedLabel'))
            ->toMatchArray(['type' => 'string', 'optional' => false])
            ->and(resolve(SubjectMethodTypeResolver::class)->resolve($invoice, 'opaque'))->toBeNull()
            ->and(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'resolveRouteBinding'))->toBeNull();
    });

    test('still answers from the model when it declares the method', function () {
        $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        expect(resolve(SubjectMethodTypeResolver::class)->resolve($scope, 'getTable'))->not->toBeNull();
    });
});

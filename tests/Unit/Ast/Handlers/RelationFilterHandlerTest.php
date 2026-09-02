<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use Illuminate\Support\Facades\Schema;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\Tag;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function relationFilterHandlerThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

it('resolves $this->relation->only([...]) to a Pick<Model, ...> reference when every key is a column', function () {
    // Mirrors CommentResource::$post_limited — Comment::post() is a BelongsTo to Post, and 'id'/
    // 'title' are both plain published columns, so the relation-filter guard emits a Pick<> arm.
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('id')),
            new ArrayItem(new String_('title')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => "Pick<Post, 'id' | 'title'>",
        'optional' => false,
        'modelFqcn' => Post::class,
    ]);
});

it('relation except() falls back to database columns only, matching Model::except() at runtime — RelationFilterHandler::resolveFilteredRelationType()', function () {
    // 'excerpt' is a pure Post accessor, not a published column, so relationFilterModelReference()
    // declines and this reaches resolveFilteredRelationType()'s except branch: HasAttributes::except()
    // iterates getAttributes() only, so 'excerpt' must never appear even though named; 'created_at' does.
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'except',
        [new Arg(new Array_([
            new ArrayItem(new String_('excerpt')),
            new ArrayItem(new String_('created_at')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => '{ id: number; title: string; content: string; user_id: number; status: StatusType; '
            .'published_at: string | null; metadata: unknown[] | null; rating: number | null; category: string; '
            .'options: Record<string, string> | null; deleted_at: string | null; updated_at: string | null; '
            .'category_id: number | null; visibility: VisibilityType | null; priority: PriorityType | null; '
            .'word_count: number | null; reading_time_minutes: number | null; featured_image_url: string | null; '
            .'is_pinned: boolean }',
        'optional' => false,
        'embeddedEnumFqcns' => [Status::class, Visibility::class, Priority::class],
        'embeddedModelFqcns' => [],
        'customImports' => [],
    ]);
});

it('relation only() still resolves a named accessor and a named relation, unlike except() — RelationFilterHandler::resolveFilteredRelationType()', function () {
    // 'title_display' (accessor) and 'comments' (relation) are both non-columns, so
    // relationFilterModelReference() declines and this reaches resolveFilteredRelationType()'s include
    // branch — only() calls getAttribute() per key, so both the accessor and the relation resolve.
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('title')),
            new ArrayItem(new String_('title_display')),
            new ArrayItem(new String_('comments')),
        ]))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => '{ title: string; title_display: string | null; comments: Comment[] }',
        'optional' => false,
        'embeddedEnumFqcns' => [],
        'embeddedModelFqcns' => [Comment::class],
        'customImports' => [],
    ]);
});

it('declines a method call whose name is not only/except', function () {
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'count',
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('emits Pick<Model, never> when except() names every published column', function () {
    $columns = Schema::getColumnListing((new Tag)->getTable());

    $type = (fn () => $this->relationFilterModelReference(Tag::class, $columns, false))
        ->call(new RelationFilterHandler);

    expect($type)->toBe('Pick<Tag, never>');
});

it('reads only(attributes: [...]) by name, matching Model::only()\'s own parameter', function () {
    $expr = new MethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([new ArrayItem(new String_('id')), new ArrayItem(new String_('title'))]), name: new Identifier('attributes'))],
    );
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]);
});

// func_get_args() on a lone named argument is [that value], so a single-key named call is a one-key list.
it('reads a lone only(attributes: \'id\') as a single-key list', function () {
    $expr = new MethodCall(new PropertyFetch(new Variable('this'), 'post'), 'only', [
        new Arg(new String_('id'), name: new Identifier('attributes')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new RelationFilterHandler)->resolve($expr, $scope, relationFilterHandlerThrowingEngine());

    expect($result)->toBe(['type' => "Pick<Post, 'id'>", 'optional' => false, 'modelFqcn' => Post::class]);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Workbench\Accounting\Http\Controllers\TwoFactorController;
use Workbench\App\Http\Controllers\CustomKeyController;
use Workbench\App\Http\Controllers\CustomKeyNameController;
use Workbench\App\Http\Controllers\CustomRouteKeyController;
use Workbench\App\Http\Controllers\Delete;
use Workbench\App\Http\Controllers\DeleteController;
use Workbench\App\Http\Controllers\DocBlockInvokableController;
use Workbench\App\Http\Controllers\DomainController;
use Workbench\App\Http\Controllers\EnumBoundController;
use Workbench\App\Http\Controllers\ExcludableController;
use Workbench\App\Http\Controllers\ExcludedController;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Controllers\InertiaNamedCollectionsController;
use Workbench\App\Http\Controllers\InertiaPaginationsController;
use Workbench\App\Http\Controllers\InertiaResourceSharedTemplate;
use Workbench\App\Http\Controllers\InertiaSingleResourceController;
use Workbench\App\Http\Controllers\InertiaTsCastsController;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\InvokableInertiaController;
use Workbench\App\Http\Controllers\InvokableModelBoundController;
use Workbench\App\Http\Controllers\InvokableModelBoundPlusController;
use Workbench\App\Http\Controllers\MiddlewareController;
use Workbench\App\Http\Controllers\MultiRouteController;
use Workbench\App\Http\Controllers\NamedInvokableController;
use Workbench\App\Http\Controllers\Nested\NestedController;
use Workbench\App\Http\Controllers\OptionalParamController;
use Workbench\App\Http\Controllers\ParameterCaseController;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Controllers\PostInertiaController;
use Workbench\App\Http\Controllers\PrimaryKeyController;
use Workbench\App\Http\Controllers\Prism\Prism\PrismController as NestedPrismController;
use Workbench\App\Http\Controllers\Prism\PrismController;
use Workbench\App\Http\Controllers\TypedParamController;
use Workbench\App\Http\Middleware\TestMiddleware;

Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

Route::get('/excludable/{id}', [ExcludableController::class, 'show'])->name('excludable.show');
Route::get('/excludable/{id}/secret', [ExcludableController::class, 'secret'])->name('excludable.secret');

Route::get('/excluded', [ExcludedController::class, 'index'])->name('excluded.index');

Route::get('/invokable', InvokableController::class);
Route::get('/named-invokable', NamedInvokableController::class)->name('named.invokable');
Route::get('/invokable-model-bound/{post}', InvokableModelBoundController::class)->name('invokable.model.bound');

Route::get('/invokable-model-plus/{post}', InvokableModelBoundPlusController::class)->name('invokable.model.bound.plus');
Route::post('/invokable-model-extra/{post}', [InvokableModelBoundPlusController::class, 'extra'])->name('invokable.model.bound.extra');
Route::delete('/invokable-model-surprise/{post}', [InvokableModelBoundPlusController::class, 'surprise'])->name('invokable.model.bound.surprise');

Route::get('/optional/{param?}', [OptionalParamController::class, 'show'])->name('optional.show');
Route::get('/optional/{one?}/{two?}', [OptionalParamController::class, 'multi'])->name('optional.multi');

Route::get('/posts/status/{status}', [EnumBoundController::class, 'byStatus'])->name('posts.byStatus');

Route::get('/articles/{article:slug}', [CustomKeyController::class, 'show'])->name('articles.show');

Route::get('/slug-posts/{slugPost}', [CustomRouteKeyController::class, 'show'])->name('slug-posts.show');

Route::get('/params/{camelCase}/camel', [ParameterCaseController::class, 'camel'])->name('params.camel');
Route::get('/params/{snake_case}/snake', [ParameterCaseController::class, 'snake'])->name('params.snake');
Route::get('/params/{SCREAMING_SNAKE}/screaming', [ParameterCaseController::class, 'screaming'])->name('params.screaming');

Route::get('/nested', [NestedController::class, 'index'])->name('nested.index');
Route::get('/nested/{id}', [NestedController::class, 'show'])->name('nested.show');

Route::get('/multi-1', [MultiRouteController::class, 'action']);
Route::get('/multi-2', [MultiRouteController::class, 'action'])->name('multi.action');

Route::middleware(TestMiddleware::class)->get('/middleware', [MiddlewareController::class, 'index'])->name('middleware.index');

Route::get('/prism', [PrismController::class, 'index'])->name('prism.index');
Route::get('/prism/nested', [NestedPrismController::class, 'nested'])->name('prism.prism.nested');

Route::delete('/items/{id}', [DeleteController::class, 'delete'])->name('items.delete');
Route::get('/items/export', [DeleteController::class, 'export'])->name('items.export');
Route::get('/delete-items', [Delete::class, 'index'])->name('delete-items.index');

Route::get('/docblock-invokable', DocBlockInvokableController::class)->name('docblock.invokable');

Route::get('/typed/{id}', [TypedParamController::class, 'showInt'])->where('id', '[0-9]+')->name('typed.show-int');
Route::get('/typed/role/{role}', [TypedParamController::class, 'showRole'])->name('typed.show-role');

Route::get('/pk-test/{uuidPost}', [PrimaryKeyController::class, 'show'])->name('pk.show');

Route::get('/key-name-test/{customKeyPost}', [CustomKeyNameController::class, 'show'])->name('key-name.show');

Route::domain('api.example.com')->group(function () {
    Route::get('/domain', [DomainController::class, 'index'])->name('domain.index');
});

Route::get('/accounting/2fa/setup', [TwoFactorController::class, 'setup'])->name('accounting.2fa-setup');
Route::post('/accounting/2fa/verify', [TwoFactorController::class, 'verify'])->name('accounting.2fa-verify');

Route::get('/inertia/profile', InvokableInertiaController::class)->name('inertia.profile');
Route::get('/inertia/dashboard', [InertiaController::class, 'dashboard'])->name('inertia.dashboard');
Route::get('/inertia/settings', [InertiaController::class, 'settings'])->name('inertia.settings');
Route::get('/inertia/about', [InertiaController::class, 'about'])->name('inertia.about');
Route::get('/inertia/conditional', [InertiaController::class, 'conditional'])->name('inertia.conditional');
Route::get('/inertia/post/{post}', [InertiaController::class, 'post'])->name('inertia.post');

Route::resource('posts-inertia', PostInertiaController::class, ['parameters' => ['posts-inertia' => 'post']]);

Route::get('/pagination/length-aware', [InertiaPaginationsController::class, 'lengthAware'])->name('pagination.length-aware');
Route::get('/pagination/simple', [InertiaPaginationsController::class, 'simple'])->name('pagination.simple');
Route::get('/pagination/cursor', [InertiaPaginationsController::class, 'cursor'])->name('pagination.cursor');

Route::get('/collection/resource-paginated-collection', [InertiaSingleResourceController::class, 'resourcePaginatedCollection'])->name('collection.resource-paginated-collection');
Route::get('/collection/resource-anon-collection', [InertiaSingleResourceController::class, 'resourceAnonymousCollection'])->name('collection.resource-anon-collection');
Route::get('/collection/resource', [InertiaSingleResourceController::class, 'resource'])->name('collection.resource');

Route::get('/same-template/resource-paginated-collection', [InertiaResourceSharedTemplate::class, 'resourcePaginatedCollection'])->name('same-template.resource-paginated-collection');
Route::get('/same-template/resource-anon-collection', [InertiaResourceSharedTemplate::class, 'resourceAnonymousCollection'])->name('same-template.resource-anon-collection');
Route::get('/same-template/resource', [InertiaResourceSharedTemplate::class, 'resource'])->name('same-template.resource');

Route::get('/collection/resource-anonymous-paginated', [InertiaNamedCollectionsController::class, 'resourceAnonymousPaginated'])->name('collection.resource-anonymous-paginated');
Route::get('/collection/resource-anonymous', [InertiaNamedCollectionsController::class, 'resourceAnonymous'])->name('collection.resource-anonymous');

Route::get('/collection/named-collection-paginated', [InertiaNamedCollectionsController::class, 'namedCollectionPaginated'])->name('collection.named-collection-paginated');
Route::get('/collection/named', [InertiaNamedCollectionsController::class, 'namedCollection'])->name('collection.named');

Route::get('/collection/flat-paginated', [InertiaNamedCollectionsController::class, 'flatCollectionPaginated'])->name('collection.flat-paginated');
Route::get('/collection/flat', [InertiaNamedCollectionsController::class, 'flatCollection'])->name('collection.flat');

Route::get('/ts-casts', [InertiaTsCastsController::class, 'index'])->name('ts-casts.index');

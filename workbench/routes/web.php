<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\CustomKeyController;
use Workbench\App\Http\Controllers\CustomRouteKeyController;
use Workbench\App\Http\Controllers\Delete;
use Workbench\App\Http\Controllers\DeleteController;
use Workbench\App\Http\Controllers\DocBlockInvokableController;
use Workbench\App\Http\Controllers\EnumBoundController;
use Workbench\App\Http\Controllers\ExcludableController;
use Workbench\App\Http\Controllers\ExcludedController;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\InvokableModelBoundController;
use Workbench\App\Http\Controllers\InvokableModelBoundPlusController;
use Workbench\App\Http\Controllers\MiddlewareController;
use Workbench\App\Http\Controllers\MultiRouteController;
use Workbench\App\Http\Controllers\NamedInvokableController;
use Workbench\App\Http\Controllers\Nested\NestedController;
use Workbench\App\Http\Controllers\OptionalParamController;
use Workbench\App\Http\Controllers\ParameterCaseController;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Controllers\Prism\Prism\PrismController as NestedPrismController;
use Workbench\App\Http\Controllers\Prism\PrismController;
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

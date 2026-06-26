<?php

declare(strict_types=1);

// require_once __DIR__.'/../../../Fixtures/InertiaUiTable/Table.php';
// require_once __DIR__.'/../../../Fixtures/InertiaUiTable/PostTable.php';
// require_once __DIR__.'/../../../Fixtures/InertiaUiTable/PostQueryTable.php';
// require_once __DIR__.'/../../../Fixtures/InertiaUiTable/PostTableCrudResource.php';
// require_once __DIR__.'/../../../Fixtures/InertiaUiTable/InertiaTableController.php';

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaTableAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\InertiaTableController;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Models\Post;

it('detects direct Inertia UI Table props without calling toArray', function () {
    $result = (new InertiaTableAnalyzer)->analyze(InertiaTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

it('detects service-layer Inertia UI Table props without calling toArray', function () {
    $result = (new InertiaTableAnalyzer)->analyze(InertiaTableController::class.'@service');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

it('resolves the model from a query() method when $resource has no default', function () {
    $result = (new InertiaTableAnalyzer)->analyze(InertiaTableController::class.'@queryBased');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class]);
});

it('uses the configured React table package when set', function () {
    config()->set('ts-publish.inertia.table_package', '@inertiaui/table-react');

    $result = (new InertiaTableAnalyzer)->analyze(InertiaTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['externalImports'])->toBe(['@inertiaui/table-react' => ['TableResource']]);
});

it('returns null for methods with no table props', function () {
    expect((new InertiaTableAnalyzer)->analyze(InertiaController::class.'@dashboard'))->toBeNull();
});

it('returns null for missing actions', function () {
    expect((new InertiaTableAnalyzer)->analyze(InertiaTableController::class.'@missing'))->toBeNull();
});

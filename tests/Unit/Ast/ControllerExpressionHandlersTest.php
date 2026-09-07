<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;

/** An analyzer over the controller profile with no backing model, as the page analyzer builds it. */
function controllerProfileAnalyzer(): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer(
        new ReflectionClass(InertiaController::class),
        null,
        'dashboard',
        ControllerExpressionHandlers::make(),
    );
}

it('inserts both controller handlers immediately before StaticCallHandler and changes nothing else', function () {
    $controller = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ControllerExpressionHandlers::make(),
    );

    $generic = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::generic(),
    );

    $expected = [];

    foreach ($generic as $class) {
        if ($class === StaticCallHandler::class) {
            $expected[] = ModelFinderHandler::class;
            $expected[] = InertiaResourcePropHandler::class;
        }

        $expected[] = $class;
    }

    expect($controller)->toBe($expected)
        ->and($controller)->toHaveCount(count($generic) + 2);
});

// Ordering pin: StaticCallHandler's last arm claims every StaticCall and never declines, so after it
// ModelFinderHandler is unreachable and `Post::find(1)` reflects as an ordinary static method.
it('tries ModelFinderHandler before StaticCallHandler for Post::find(1)', function () {
    $expr = new StaticCall(new Name(Post::class), 'find', [new Arg(new Int_(1))]);

    expect(controllerProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'Post | null',
        'optional' => false,
        'modelFqcn' => Post::class,
    ]);
});

// Ordering pin: same last-arm problem — StaticCallHandler types ::collection() as the element array,
// which is the nested-resource answer, not the page-prop payload.
it('tries InertiaResourcePropHandler before StaticCallHandler for WarehouseResource::collection()', function () {
    $expr = new StaticCall(new Name(WarehouseResource::class), 'collection', [new Arg(new Variable('rows'))]);

    expect(controllerProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'AnonymousResourceCollection<WarehouseResource>',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
        'customImports' => ['@tolki/types' => ['AnonymousResourceCollection']],
    ]);
});

// Ordering pin: NewResourceHandler resolves a collection to its collected element array, so running
// first it would answer `PostResource[]` and the PostCollection interface would never be referenced.
it('tries InertiaResourcePropHandler before NewResourceHandler for new PostCollection()', function () {
    $expr = new New_(new Name(PostCollection::class), [new Arg(new Variable('rows'))]);

    expect(controllerProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'PostCollection',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
    ]);
});

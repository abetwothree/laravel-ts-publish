<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiTypedClosurePageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiNewValueEdgesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiNodeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiUntypedHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\InheritingChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\EveryWayNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Stamp;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\UiShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Http\Controllers\UiDashboardController;
use Workbench\App\Http\Resources\UiPanelResource;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Plain;

afterEach(function () {
    JsonResource::flushState();
});

it('publishes the issue example as the override, with one import', function () {
    $data = pageData(UiDashboardController::class.'@index');

    expect($data['pageType'])->toBe('Inertia.SharedData & { chart: UiNode, widgets: UiNode[], maybe: UiNode | null, coll: UiNode[], composed: ChartType, gauge: UiGauge, pennies: UiAmount, deferred?: UiGauge, optional?: ChartType }')
        ->and($data['externalImports'])->toMatchArray(['@js/types/ui' => ['UiNode', 'UiGauge', 'UiAmount']])
        ->and(new RouteTransformer(UiDashboardController::class)->typeImports)
        ->toBe(['@js/types/ui' => ['UiAmount', 'UiGauge', 'UiNode'], '../../packages/ui' => ['ChartType']])
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('sends the issue example in the shapes it publishes', function () {
    $props = function (bool $maybe): stdClass {
        $request = Request::create('/ui/dashboard', 'GET', $maybe ? ['maybe' => 1] : []);
        $request->headers->set('X-Inertia', 'true');

        return json_decode((string) new UiDashboardController()->index($request)->toResponse($request)->getContent(), false)->props;
    };

    $sent = $props(true);

    expect($sent->chart)->toBeObject()
        ->and($sent->widgets)->toBeArray()->toHaveCount(2)
        ->and($sent->widgets[0])->toBeObject()
        ->and($sent->maybe)->toBeObject()
        ->and($props(false)->maybe)->toBeNull()
        ->and($sent->coll)->toBeArray()->toHaveCount(2)
        ->and($sent->coll[0])->toBeObject()
        ->and($sent->composed)->toBeObject()
        ->and($sent->gauge)->toBeObject()
        ->and($sent->pennies)->toBeObject()
        ->and($sent)->not->toHaveProperty('deferred')
        ->and($sent)->not->toHaveProperty('optional')
        ->and(inertiaProps(new UiDashboardController()->index(request()), 'Ui/Dashboard', 'deferred,optional'))
        ->deferred->toBeObject()
        ->optional->toBeObject();
});

it('keeps an override and its import through a typed closure', function () {
    $data = pageData(UiTypedClosurePageController::class.'@index');

    expect($data['pageType'])->toBe('Inertia.SharedData & { gauge: UiGauge, deferred?: UiGauge, optional?: ChartType, panel: UiNode | null, maybe: UiNode | null, plain: { a: number } }')
        ->and($data['externalImports'])->toBe([
            '@js/types/ui' => ['UiGauge', 'UiNode'],
            ComposedClassTypes::marker(Chart::class) => ['ChartType'],
        ])
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('keeps each typed closure\'s own override import where only its declared type answers', function (string $declared, array $expected) {
    $return = new AstParser()->parseSource("<?php return fn (): {$declared} => app('ui.node');")[0];

    expect($return)->toBeInstanceOf(Return_::class)
        ->and(new ResourceAstAnalyzer(new ReflectionClass(UiNodeResource::class), null)->resolve($return->expr))->toBe($expected);
})->with([
    'an own override' => ['\\'.Gauge::class, ['type' => 'UiGauge', 'customImports' => ['@js/types/ui' => ['UiGauge']], 'optional' => false]],
    'a composed class, its module named by the marker' => [
        '\\'.Chart::class, ['type' => 'ChartType', 'customImports' => [ComposedClassTypes::marker(Chart::class) => ['ChartType']], 'optional' => false],
    ],
    'a nullable inherited override' => ['?\\'.Panel::class, ['type' => 'UiNode | null', 'customImports' => ['@js/types/ui' => ['UiNode']], 'optional' => false]],
    'an override already nullable, its null kept once' => [
        '?\\'.MaybeNode::class, ['type' => 'UiNode | null', 'customImports' => ['@js/types/ui' => ['UiNode']], 'optional' => false],
    ],
    'a class with no override, which keeps its shape' => ['\\'.Plain::class, ['type' => '{ a: number }', 'optional' => false]],
]);

// The closures read a container id, so the oracle binds each one to a value of its declared class.
it('sends each typed closure in the shape it publishes', function () {
    app()->bind('ui.gauge', fn (): Gauge => new Gauge);
    app()->bind('ui.chart', fn (): Chart => new Chart);
    app()->bind('ui.panel', fn (): Panel => new Panel);
    app()->bind('ui.maybe', fn (): MaybeNode => new MaybeNode);
    app()->bind('ui.plain', fn (): Plain => new Plain);
    $page = new UiTypedClosurePageController()->index();
    $sent = inertiaProps($page);

    expect($sent)->gauge->toBeObject()
        ->panel->toBeObject()
        ->maybe->toBeObject()
        ->plain->toEqual((object) ['a' => 1])
        ->and($sent)->not->toHaveProperty('deferred')
        ->and($sent)->not->toHaveProperty('optional')
        ->and(inertiaProps($page, 'Ui/TypedClosures', 'deferred,optional'))
        ->deferred->toBeObject()
        ->optional->toBeObject();
});

it('publishes a new of an overridden class in a resource and in shared data', function () {
    expect(resourceProps(UiPanelResource::class))->toMatchArray([
        'panel' => 'UiNode', 'panels' => 'UiNode[]', 'when_panel?' => 'UiNode', 'maybe_chart' => 'ChartType | null',
    ])->and(sharedProps(UiShareMiddleware::class))->toMatchArray(['node' => 'UiNode', 'pennies' => 'UiAmount', 'chart' => 'ChartType']);
});

it('sends a new of an overridden class from a resource and from shared data in the shapes they publish', function () {
    $body = fn (array $query): stdClass => sentAsJson(new UiPanelResource(null)->resolve(Request::create('/', 'GET', $query)));

    expect($body(['chart' => 1])->maybe_chart)->toBeObject()
        ->and($body([])->maybe_chart)->toBeNull()
        ->and($body([])->panel)->toBeObject()
        ->and($body([])->panels)->toBeArray()->toHaveCount(2)
        ->and($body(['panel' => 1])->when_panel)->toBeObject()
        ->and($body([]))->not->toHaveProperty('when_panel')
        ->and(sentAsJson(new UiShareMiddleware()->share(request())))
        ->node->toBeObject()
        ->pennies->toBeObject()
        ->chart->toBeObject();
});

it('publishes the override for a class every serialization branch would answer', function (string $class, array $expected) {
    expect(new ValueResolver()->resolveSerializedNew(new New_(new Name($class))))->toMatchArray([...$expected, 'keepsKeys' => true]);
})->with([
    'an Arrayable, a string jsonSerialize(), a __toString(), a response and an Inertia property' => [
        EveryWayNode::class, ['type' => 'UiNode', 'customImports' => ['@js/types/ui' => ['UiNode']]],
    ],
    'a Carbon date, whose string json_encode() writes' => [Stamp::class, ['type' => 'UiStamp', 'customImports' => ['@js/types/ui' => ['UiStamp']]]],
    'a composed class, its module named by the marker' => [
        Chart::class, ['type' => 'ChartType', 'customImports' => [ComposedClassTypes::marker(Chart::class) => ['ChartType']]],
    ],
]);

it('keeps the answer of a new of a class with no override, a model and an API resource', function (string $class) {
    expect(new ValueResolver()->resolveSerializedNew(new New_(new Name($class))))->toBeNull();
})->with([
    'an Arrayable with no override' => [Plain::class],
    'a model with an inherited override, whose JSON depends on its attributes' => [InheritingChildModel::class],
    'an API resource with an override, which publishes as a resource' => [UiNodeResource::class],
]);

it('publishes a new of an overridden class in every shape a resource reads it', function () {
    expect(resourceProps(UiNewValueEdgesResource::class))->toBe([
        'every_way' => 'UiNode',
        'stamp' => 'UiStamp',
        'maybe_node' => 'UiNode | null',
        'default_gauge?' => 'UiGauge',
        'model' => 'unknown',
        'plain' => 'unknown',
        'default_resource?' => 'null',
    ])->and(new ResourceTransformer(UiNewValueEdgesResource::class)->typeImports)
        ->toBe(['@js/types/ui' => ['UiGauge', 'UiNode', 'UiStamp']]);
});

// An override states the frontend's type, so the oracle checks only the shapes a key's publication adds.
it('sends a new of an overridden class in every shape a resource reads it', function () {
    $body = fn (array $query): stdClass => sentAsJson(new UiNewValueEdgesResource(null)->resolve(Request::create('/', 'GET', $query)));

    expect($body(['node' => 1])->maybe_node)->toBeObject()
        ->and($body([])->maybe_node)->toBeNull()
        ->and($body([])->default_gauge)->toBeObject();
});

it('publishes a new an untyped helper returns as its override, with its import', function () {
    expect(resourceProps(UiUntypedHelperResource::class))->toBe(['panel' => 'UiNode'])
        ->and(new ResourceTransformer(UiUntypedHelperResource::class)->typeImports)->toBe(['@js/types/ui' => ['UiNode']])
        ->and(sentAsJson(new UiUntypedHelperResource(null)->resolve(request()))->panel)->toBeObject();
});

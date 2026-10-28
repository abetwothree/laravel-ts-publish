<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaSharedDataAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiNewValueEdgesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiNodeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiUntypedHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\InheritingChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\EveryWayNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Stamp;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\UiShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use Workbench\App\Http\Controllers\UiDashboardController;
use Workbench\App\Http\Resources\UiPanelResource;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Plain;

/**
 * Each property an API resource publishes, as `name => type`, `?` marking an optional key.
 *
 * @param  class-string<JsonResource>  $class
 * @return array<string, string>
 */
function resourceProps(string $class): array
{
    $props = [];

    foreach (new ResourceTransformer($class)->data()->properties as $name => $property) {
        $props[$name.($property['optional'] ? '?' : '')] = $property['type'];
    }

    return $props;
}

/**
 * Each prop a share middleware publishes, as `name => type`, read from the type the shared data analyzer writes.
 *
 * @param  class-string  $middleware
 * @return array<string, string>
 */
function sharedProps(string $middleware): array
{
    $analyzer = Mockery::mock(InertiaSharedDataAnalyzer::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $analyzer->shouldReceive('discoverMiddlewareClass')->andReturn($middleware);

    return array_column(TsTypeShape::members($analyzer->analyze()['sharedPageProps'] ?? '') ?? [], 1, 0);
}

/**
 * What PHP sends for a value, decoded as the frontend reads it.
 */
function sentAsJson(mixed $value): mixed
{
    return json_decode((string) json_encode($value), false);
}

afterEach(function () {
    JsonResource::flushState();
});

it('publishes the issue example as the override, with one import', function () {
    $data = pageData(UiDashboardController::class.'@index');

    expect($data['pageType'])->toBe('Inertia.SharedData & { chart: UiNode, widgets: UiNode[], maybe: UiNode | null, coll: UiNode[], composed: ChartType, gauge: UiGauge, pennies: UiAmount }')
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

    expect($props(true)->chart)->toBeObject()
        ->and($props(true)->widgets)->toBeArray()->toHaveCount(2)
        ->and($props(true)->widgets[0])->toBeObject()
        ->and($props(true)->maybe)->toBeObject()
        ->and($props(false)->maybe)->toBeNull()
        ->and($props(true)->coll)->toBeArray()->toHaveCount(2)
        ->and($props(true)->composed)->toBeObject()
        ->and($props(true)->gauge)->toBeObject()
        ->and($props(true)->pennies)->toBeObject();
});

it('publishes a new of an overridden class in a resource and in shared data', function () {
    expect(resourceProps(UiPanelResource::class))->toMatchArray([
        'panel' => 'UiNode', 'panels' => 'UiNode[]', 'when_panel?' => 'UiNode', 'maybe_chart' => 'ChartType | null', 'plain' => 'unknown',
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
        'default_panel?' => 'UiNode',
        'model' => 'unknown',
        'default_resource?' => 'null',
    ])->and(new ResourceTransformer(UiNewValueEdgesResource::class)->typeImports)
        ->toBe(['@js/types/ui' => ['UiNode', 'UiStamp']]);
});

// An override states the frontend's type, so the oracle checks only the shapes a key's publication adds.
it('sends a new of an overridden class in every shape a resource reads it', function () {
    $body = fn (array $query): stdClass => sentAsJson(new UiNewValueEdgesResource(null)->resolve(Request::create('/', 'GET', $query)));

    expect($body(['node' => 1])->maybe_node)->toBeObject()
        ->and($body([])->maybe_node)->toBeNull()
        ->and($body([])->default_panel)->toBeObject();
});

it('publishes a new an untyped helper returns as its override, with its import', function () {
    expect(resourceProps(UiUntypedHelperResource::class))->toBe(['panel' => 'UiNode'])
        ->and(new ResourceTransformer(UiUntypedHelperResource::class)->typeImports)->toBe(['@js/types/ui' => ['UiNode']])
        ->and(sentAsJson(new UiUntypedHelperResource(null)->resolve(request()))->panel)->toBeObject();
});

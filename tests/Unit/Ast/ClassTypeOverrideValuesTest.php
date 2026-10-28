<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\IconShade;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedLevel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedRank;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\RenamedTone;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiTonePageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiTypedClosurePageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MappedLevelReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiNewValueEdgesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiNodeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiToneReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiUntypedHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\InheritingChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MappedLevelHolder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiToneHolder;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\EveryWayNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Stamp;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\UiShareMiddleware;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\TypeScriptMap;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Enums\UiTone;
use Workbench\App\Http\Controllers\UiDashboardController;
use Workbench\App\Http\Resources\UiPanelResource;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\HasIcon;
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

        return inertiaProps(new UiDashboardController()->index($request), $request);
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
        ->and(inertiaProps(new UiDashboardController()->index(request()), partialReload('Ui/Dashboard', 'deferred,optional')))
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
        ->and(inertiaProps($page, partialReload('Ui/TypedClosures', 'deferred,optional')))
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

it("imports an overridden enum case's type", function () {
    config()->set('ts-publish.output_to_files', false);
    $page = pageData(UiTonePageController::class.'@index');
    $palette = pageData(UiTonePageController::class.'@palette');

    expect(resourceProps(UiPanelResource::class))->toMatchArray(['tone' => 'UiToneDto'])
        ->and(new ResourceWriter(new Filesystem)->write(new ResourceTransformer(UiPanelResource::class)))
        ->toContain("import type { UiNode, UiToneDto } from '@js/types/ui';")
        ->toContain('    tone: UiToneDto;')
        ->and($page['pageType'])->toBe('Inertia.SharedData & { tone: UiToneDto, tones: UiToneDto[] }')
        ->and($page['externalImports'])->toBe(['@js/types/ui' => ['UiToneDto']])
        ->and($palette['pageType'])->toBe('Inertia.SharedData & { palette: { main: UiToneDto; muted: UiToneDto[] } }')
        ->and($palette['externalImports'])->toBe(['@js/types/ui' => ['UiToneDto']])
        ->and(AnalysisWarnings::all())->toBe([]);
});

it("sends an overridden enum's case in the shape it publishes", function () {
    expect(sentAsJson(new UiPanelResource(null)->resolve(request()))->tone)->toBe('bright')
        ->and(inertiaProps(new UiTonePageController()->index()))
        ->tone->toBe('bright')
        ->tones->toBe(['bright', 'dim'])
        ->and(inertiaProps(new UiTonePageController()->palette())->palette)
        ->toEqual((object) ['main' => 'bright', 'muted' => ['dim']]);
});

describe("an overridden enum's own names", function () {
    beforeEach(function () {
        Schema::dropIfExists('ui_tone_holders');
        Schema::create('ui_tone_holders', function (Blueprint $table) {
            $table->id();
            $table->string('tone');
            $table->string('maybe_tone')->nullable();
            $table->string('shade');
        });
        $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([UiTone::class, RenamedTone::class]));
    });

    it('keeps them where the enum publishes', function () {
        expect(resourceProps(UiPanelResource::class)['tone_resource'])->toBe('AsEnum<typeof UiTone>')
            ->and(resourceProps(UiToneReadsResource::class))->toBe([
                'tone' => 'UiToneDto',
                'column' => 'UiToneDto',
                'from' => 'AsEnum<typeof UiTone>',
                'column_resource' => 'AsEnum<typeof UiTone>',
                'maybe_resource' => 'AsEnum<typeof UiTone> | null',
                'accessor_resource' => 'AsEnum<typeof UiTone>',
                'shade_resource' => 'AsEnum<typeof Shade>',
                'shade_column_resource' => 'AsEnum<typeof Shade>',
                'either' => 'AsEnum<typeof UiTone> | UiToneDto',
                'value' => 'UiToneType',
                'name' => 'UiToneKind',
                'shade_name' => 'ShadeKind',
                'default_value?' => 'UiToneType',
                'coalesce_resource' => 'AsEnum<typeof UiTone>',
                'constant_resource' => 'AsEnum<typeof UiTone>',
                'helper_resource' => 'AsEnum<typeof UiTone>',
                'default_resource?' => 'AsEnum<typeof UiTone>',
                'constant_list' => 'AsEnum<typeof UiTone>[]',
                'literal_list' => 'AsEnum<typeof UiTone>[]',
                'local_list' => 'AsEnum<typeof UiTone>[]',
                'matched_resource' => 'AsEnum<typeof UiTone>',
                'untyped_resource' => 'AsEnum<typeof UiTone>',
                'values_list' => 'AsEnum<typeof UiTone>[]',
                'collected_list' => 'AsEnum<typeof UiTone>[]',
                'either_list' => 'AsEnum<typeof UiTone>[]',
            ]);
    });

    it('sends the shapes they publish', function () {
        $holder = UiToneHolder::query()->create(['tone' => UiTone::Bright, 'shade' => RenamedTone::Dark])->fresh();
        $body = fn (array $query): stdClass => sentAsJson(new UiToneReadsResource($holder)->resolve(Request::create('/', 'GET', $query)));
        $sent = $body(['wrap' => 1, 'value' => 1, 'pick' => 1]);
        $bright = (object) ['name' => 'Bright', 'value' => 'bright', 'backed' => true];
        $dim = (object) ['name' => 'Dim', 'value' => 'dim', 'backed' => true];

        expect($sent)
            ->tone->toBe('dim')
            ->column->toBe('bright')
            ->from->toEqual((object) ['name' => 'Dim', 'value' => 'dim', 'backed' => true])
            ->column_resource->toEqual((object) ['name' => 'Bright', 'value' => 'bright', 'backed' => true])
            ->maybe_resource->toBeNull()
            ->accessor_resource->toEqual((object) ['name' => 'Dim', 'value' => 'dim', 'backed' => true])
            ->shade_resource->toEqual((object) ['name' => 'Light', 'value' => 'light', 'backed' => true])
            ->shade_column_resource->toEqual((object) ['name' => 'Dark', 'value' => 'dark', 'backed' => true])
            ->either->toEqual((object) ['name' => 'Bright', 'value' => 'bright', 'backed' => true])
            ->value->toBe('bright')
            ->name->toBe('Bright')
            ->shade_name->toBe('Dark')
            ->default_value->toBe('dim')
            ->coalesce_resource->toEqual($dim)
            ->constant_resource->toEqual($dim)
            ->helper_resource->toEqual($bright)
            ->default_resource->toEqual($dim)
            ->constant_list->toEqual([$bright, $dim])
            ->literal_list->toEqual([$bright, $dim])
            ->local_list->toEqual([$dim, $bright])
            ->matched_resource->toEqual($bright)
            ->untyped_resource->toEqual($bright)
            ->values_list->toEqual([$bright, $dim])
            ->collected_list->toEqual([$dim])
            ->either_list->toEqual([$bright, $dim])
            ->and($body([]))
            ->either_list->toBe([])
            ->either->toBe('dim')
            ->matched_resource->toEqual($dim)
            ->not->toHaveProperty('default_value')
            ->not->toHaveProperty('default_resource');
    });
});

describe("a custom_ts_mappings enum's own names", function () {
    beforeEach(function () {
        Schema::dropIfExists('mapped_level_holders');
        Schema::create('mapped_level_holders', function (Blueprint $table) {
            $table->id();
            $table->string('level');
            $table->string('rank');
        });
        config()->set('ts-publish.custom_ts_mappings', [MappedLevel::class => 'string', MappedRank::class => 'string']);
        TypeScriptMap::reset();
        $this->mock(EnumsCollector::class)->shouldReceive('collect')->andReturn(collect([MappedLevel::class, MappedRank::class]));
    });

    it('keeps them where the enum publishes, beside the mapping a case and a column take', function () {
        expect(resourceProps(MappedLevelReadsResource::class))->toBe([
            'level' => 'string',
            'column' => 'string',
            'case_resource' => 'AsEnum<typeof MappedLevel>',
            'from_resource' => 'AsEnum<typeof MappedLevel>',
            'column_resource' => 'AsEnum<typeof MappedLevel>',
            'maybe_resource' => 'AsEnum<typeof MappedLevel> | null',
            'value' => 'MappedLevelType',
            'rank_value' => 'RankType',
            'rank_resource' => 'AsEnum<typeof Rank>',
            'rank_column_resource' => 'AsEnum<typeof Rank>',
        ]);
    });

    it('sends the shapes they publish', function () {
        $holder = MappedLevelHolder::query()->create(['level' => MappedLevel::High, 'rank' => MappedRank::Bottom])->fresh();
        $body = fn (array $query): stdClass => sentAsJson(new MappedLevelReadsResource($holder)->resolve(Request::create('/', 'GET', $query)));

        expect($body([]))
            ->level->toBe('low')
            ->column->toBe('high')
            ->case_resource->toEqual((object) ['name' => 'Low', 'value' => 'low', 'backed' => true])
            ->from_resource->toEqual((object) ['name' => 'High', 'value' => 'high', 'backed' => true])
            ->column_resource->toEqual((object) ['name' => 'High', 'value' => 'high', 'backed' => true])
            ->maybe_resource->toEqual((object) ['name' => 'High', 'value' => 'high', 'backed' => true])
            ->value->toBe('low')
            ->rank_value->toBe('top')
            ->rank_resource->toEqual((object) ['name' => 'Top', 'value' => 'top', 'backed' => true])
            ->rank_column_resource->toEqual((object) ['name' => 'Bottom', 'value' => 'bottom', 'backed' => true])
            ->and($body(['none' => 1])->maybe_resource)->toBeNull();
    });
});

it("keeps an enum's own override alone", function () {
    $page = pageData(UiTonePageController::class.'@shade');

    expect($page['pageType'])->toBe('Inertia.SharedData & { shade: UiNode }')
        ->and($page['externalImports'])->toBe(['@js/types/ui' => ['UiNode']])
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => IconShade::class,
            'message' => 'Publishes the #[TsType] [UiNode] alone and leaves out [UiIcon] from ['.HasIcon::class.']: '
                .'a model, API resource, broadcast event, form request, route controller or enum publishes only its own or a parent class\'s.',
        ]])
        ->and(inertiaProps(new UiTonePageController()->shade())->shade)->toBe('light');
});

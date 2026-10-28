<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\IconShade;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiPanelPageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiPanelHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AnnouncingDraft;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AnnouncingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\InheritingChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\OverridingChildModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiReading;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\DialOverMaybe;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Diamond;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\LoneChart;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeNode;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MutedThing;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\QuietThing;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Twice;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Events\UiBoardUpdated;
use Workbench\App\Packages\Ui\Badge;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Concerns\AnnouncesHello;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Node;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;
use Workbench\App\Packages\Ui\Plain;
use Workbench\App\Packages\Ui\Spark;

beforeEach(function () {
    ClassTypeOverride::flush();
    ComposedClassTypes::flush();
});

afterEach(function () {
    ClassTypeOverride::flush();
    ComposedClassTypes::flush();
});

it('lists a class contributors in order: own, traits, interfaces, then the parent', function () {
    expect(array_column(ClassTypeOverride::contributors(Chart::class), 'type'))->toBe(['UiHello', 'UiIcon', 'UiNode'])
        ->and(array_column(ClassTypeOverride::contributors(Dial::class), 'type'))->toBe(['UiDial', 'UiNode'])
        ->and(array_column(ClassTypeOverride::contributors(Gauge::class), 'type'))->toBe(['UiGauge'])
        ->and(ClassTypeOverride::contributors(Plain::class))->toBe([]);
});

it('publishes one contributor as written and two or more as the composed type', function () {
    expect(LaravelTsPublish::toTsType(Panel::class))->toMatchArray(['type' => 'UiNode', 'customImports' => ['@js/types/ui' => ['UiNode']]])
        ->and(LaravelTsPublish::toTsType(Badge::class)['type'])->toBe('UiIcon')
        ->and(LaravelTsPublish::toTsType(HasIcon::class)['type'])->toBe('UiIcon')
        ->and(LaravelTsPublish::toTsType(Chart::class))->toMatchArray([
            'type' => 'ChartType',
            'customImports' => [ComposedClassTypes::marker(Chart::class) => ['ChartType']],
        ])
        ->and(ComposedClassTypes::registered())->toContain(Chart::class)
        ->and(LaravelTsPublish::toTsType('?'.Pennies::class)['type'])->toBe('UiAmount | null')
        ->and(LaravelTsPublish::toTsType(Plain::class)['type'])->toBe('{ a: number }');
});

it('names each contributor by the class, trait or interface that declares it', function () {
    expect(ClassTypeOverride::contributors(Chart::class))->toBe([
        ['type' => 'UiHello', 'import' => '@js/types/ui', 'declaredBy' => AnnouncesHello::class],
        ['type' => 'UiIcon', 'import' => '@js/types/ui', 'declaredBy' => HasIcon::class],
        ['type' => 'UiNode', 'import' => '@js/types/ui', 'declaredBy' => Node::class],
    ])->and(ClassTypeOverride::contributors(Spark::class)[0])->toBe(['type' => 'string', 'import' => null, 'declaredBy' => Spark::class]);
});

it('resolves a composed class with every contributor and none left out', function () {
    expect(ClassTypeOverride::resolve(Chart::class))->toBe([
        'class' => Chart::class,
        'type' => 'ChartType',
        'customImports' => [ComposedClassTypes::marker(Chart::class) => ['ChartType']],
        'contributors' => ClassTypeOverride::contributors(Chart::class),
        'composed' => true,
        'leftOut' => [],
    ])->and(ClassTypeOverride::resolve(Plain::class))->toBeNull()
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('lists a contributor reached twice once', function () {
    $twice = array_column(ClassTypeOverride::contributors(Twice::class), 'type');

    // Only the count is pinned: a re-declared interface takes the place reflection gives it (docs/known-gaps.md).
    expect($twice)->toHaveCount(3)->toContain('UiGlow', 'UiBase', 'UiIcon')
        ->and(ClassTypeOverride::contributors(Diamond::class))->toBe([
            ['type' => 'UiIcon', 'import' => '@js/types/ui', 'declaredBy' => HasIcon::class],
        ]);
});

it('does not repeat null for a nullable override read as nullable', function () {
    expect(LaravelTsPublish::toTsType('?'.MaybeNode::class))->toMatchArray([
        'type' => 'UiNode | null',
        'customImports' => ['@js/types/ui' => ['UiNode']],
    ]);
});

it('lets the nearest contributor win when one cannot be extended', function (string $class, string $type, string $leftOut) {
    $published = LaravelTsPublish::toTsType($class);
    LaravelTsPublish::toTsType($class);

    expect($published['type'])->toBe($type)
        ->and(ClassTypeOverride::resolve($class))->toMatchArray(['type' => $type, 'composed' => false])
        ->and(array_column(ClassTypeOverride::resolve($class)['leftOut'] ?? [], 'type'))->toBe([$leftOut])
        ->and(ComposedClassTypes::registered())->toBe([])
        ->and(AnalysisWarnings::all())->toHaveCount(1)
        ->and(AnalysisWarnings::all()[0]['subject'])->toBe($class)
        ->and(AnalysisWarnings::all()[0]['message'])->toContain("[{$leftOut}]");
})->with([
    'its own keyword over a parent' => [Spark::class, 'string', 'UiNode'],
    'its own over a parent\'s union' => [DialOverMaybe::class, 'UiDial', 'UiNode | null'],
]);

it('warns which contributors were left out and why', function () {
    ClassTypeOverride::resolve(Spark::class);

    expect(AnalysisWarnings::all())->toBe([[
        'subject' => Spark::class,
        'message' => 'Publishes the #[TsType] [string] alone and leaves out [UiNode] from ['.Node::class.']: '
            .'a composed type cannot extend [string].',
    ]]);
});

it('records the class that declares an inherited override', function () {
    $files = array_map(
        fn (string $class): string|false => new ReflectionClass($class)->getFileName(),
        [Chart::class, Node::class, HasIcon::class, AnnouncesHello::class],
    );

    DependencyRecorder::start();
    ClassTypeOverride::resolve(Chart::class);
    $first = DependencyRecorder::paths();

    DependencyRecorder::start();
    ClassTypeOverride::resolve(Chart::class);
    $memoized = DependencyRecorder::paths();
    DependencyRecorder::stop();
    DependencyRecorder::reset();

    expect($first)->toContain(...$files)
        ->and($memoized)->toBe($first);
});

it('stands a class alone with inherit: false', function (string $class, string $type) {
    expect(array_column(ClassTypeOverride::contributors($class), 'type'))->toBe([$type])
        ->and(LaravelTsPublish::toTsType($class))->toMatchArray(['type' => $type, 'customImports' => ['@js/types/ui' => [$type]]]);
})->with([
    'at a class' => [LoneChart::class, 'UiLone'],
    'at a trait' => [QuietThing::class, 'UiQuiet'],
    'at an interface' => [MutedThing::class, 'UiMuted'],
]);

it("publishes an owned class's own override, else its nearest parent class's, with no warning", function (string $class, string $type) {
    expect(LaravelTsPublish::toTsType($class))->toMatchArray([
        'type' => $type,
        'customImports' => ['@js/types/ui' => [$type]],
    ])->and(ClassTypeOverride::resolve($class))->toMatchArray(['composed' => false, 'leftOut' => []])
        ->and(array_column(ClassTypeOverride::resolve($class)['contributors'] ?? [], 'type'))->toBe([$type])
        ->and(AnalysisWarnings::all())->toBe([]);
})->with([
    'its parent model\'s' => [InheritingChildModel::class, 'UiNode'],
    'its own, over its parent model\'s' => [OverridingChildModel::class, 'UiDial'],
]);

it("leaves a trait's or interface's override on an owned class out, with the warning", function (string $class, string $leftOut, string $declaredBy) {
    expect(LaravelTsPublish::toTsType($class))->toMatchArray([
        'type' => 'UiNode',
        'customImports' => ['@js/types/ui' => ['UiNode']],
    ])->and(ClassTypeOverride::resolve($class))->toMatchArray(['composed' => false])
        ->and(array_column(ClassTypeOverride::resolve($class)['leftOut'] ?? [], 'type'))->toBe([$leftOut])
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => $class,
            'message' => "Publishes the #[TsType] [UiNode] alone and leaves out [{$leftOut}] from [{$declaredBy}]: "
                .'a model, API resource, broadcast event, form request, route controller or enum publishes only its own or a parent class\'s.',
        ]]);
})->with([
    'a model using a trait' => [AnnouncingPost::class, 'UiHello', AnnouncesHello::class],
    'an enum implementing an interface' => [IconShade::class, 'UiIcon', HasIcon::class],
]);

it("leaves out a trait's override on an owned class with none of its own", function () {
    expect(ClassTypeOverride::resolve(AnnouncingDraft::class))->toBeNull()
        ->and(LaravelTsPublish::toTsType(AnnouncingDraft::class)['type'])->toBe('AnnouncingDraft')
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => AnnouncingDraft::class,
            'message' => 'Publishes no #[TsType] and leaves out [UiHello] from ['.AnnouncesHello::class.']: '
                .'a model, API resource, broadcast event, form request, route controller or enum publishes only its own or a parent class\'s.',
        ]]);
});

it('reads a mis-cased owned class as its declared name', function () {
    class_exists(OverridingChildModel::class);

    expect(LaravelTsPublish::toTsType(strtolower(OverridingChildModel::class)))->toMatchArray([
        'type' => 'UiDial',
        'customImports' => ['@js/types/ui' => ['UiDial']],
    ])->and(AnalysisWarnings::all())->toBe([]);
});

it('reads a mis-cased composed class as its declared name', function () {
    class_exists(Chart::class);

    expect(LaravelTsPublish::toTsType(strtolower(Chart::class)))->toMatchArray([
        'type' => 'ChartType',
        'customImports' => [ComposedClassTypes::marker(Chart::class) => ['ChartType']],
    ])->and(LaravelTsPublish::toTsType('\\'.Chart::class)['type'])->toBe('ChartType')
        ->and(LaravelTsPublish::toTsType(Chart::class)['type'])->toBe('ChartType')
        ->and(ComposedClassTypes::registered())->toBe([Chart::class]);
});

describe('a declared value of an overridden class publishes the override', function () {
    /** What a JSON response sends for a value, decoded to objects and lists. */
    $sent = fn (mixed $value): mixed => json_decode((string) json_encode($value), false);

    it('in an API resource helper declaring the class', function () use ($sent) {
        $result = resolve(AstEngine::class)->analyze(UiPanelHelperResource::class, 'toArray', null, 'tests/fixtures');
        $body = $sent(new UiPanelHelperResource(null)->resolve(request()));

        expect(array_column($result->properties, 'type', 'name')['panel'])->toBe('UiNode')
            ->and($result->typeImports)->toBe(['@js/types/ui' => ['UiNode']])
            ->and($body->panel)->toBeObject();
    });

    it('in a @return list of the class', function () use ($sent) {
        $result = resolve(AstEngine::class)->analyze(UiPanelHelperResource::class, 'toArray', null, 'tests/fixtures');
        $body = $sent(new UiPanelHelperResource(null)->resolve(request()));

        expect(array_column($result->properties, 'type', 'name')['panels'])->toBe('UiNode[]')
            ->and($body->panels)->toBeArray()->toHaveCount(2)
            ->and($body->panels[0])->toBeObject();
    });

    it('in an Inertia page helper declaring the class nullable', function () {
        $page = new InertiaPageAnalyzer()->analyze(['uses' => UiPanelPageController::class.'@show']);
        $props = function (bool $flag): stdClass {
            $request = Request::create('/ui', 'GET', $flag ? ['flag' => 1] : []);
            $request->headers->set('X-Inertia', 'true');
            app()->instance('request', $request);

            $response = new UiPanelPageController()->show($request)->toResponse($request);

            return json_decode((string) $response->getContent(), false)->props;
        };

        expect($page['pageType'])->toContain('maybe: UiNode | null')
            ->and($page['externalImports'])->toBe(['@js/types/ui' => ['UiNode']])
            ->and($props(true)->maybe)->toBeObject()
            ->and($props(false)->maybe)->toBeNull();
    });

    it('in a broadcast event property', function () use ($sent) {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => UiBoardUpdated::class]);
        $event = new UiBoardUpdated(new Panel, new Gauge, new Pennies(5), new Badge);
        $broadcast = new BroadcastEvent($event);
        $payload = $sent(new ReflectionMethod($broadcast, 'getPayloadFromEvent')->invoke($broadcast, $event));

        expect(array_slice(array_map(fn (array $property): string => $property['type'], $transformer->properties), 0, 5))->toBe([
            'panel' => 'UiNode',
            'gauge' => 'UiGauge',
            'pennies' => 'UiAmount',
            'badge' => 'UiIcon',
            'maybe' => 'UiNode | null',
        ])->and($transformer->typeImports['@js/types/ui'])->toBe(['UiAmount', 'UiGauge', 'UiIcon', 'UiNode'])
            ->and($payload->panel)->toBeObject()
            ->and($payload->gauge)->toBeObject()
            ->and($payload->pennies)->toBeObject()
            ->and($payload->badge)->toBeObject()
            ->and($payload->maybe)->toBeNull();
    });

    describe('on a model', function () use ($sent) {
        beforeEach(function () {
            Schema::dropIfExists('ui_readings');
            Schema::create('ui_readings', function (Blueprint $table) {
                $table->id();
                $table->integer('pennies');
            });
        });

        it('in a cast whose get() returns the class', function () use ($sent) {
            $data = new ModelTransformer(UiReading::class)->data()->toArray();
            $row = $sent(UiReading::query()->create(['pennies' => 5])->fresh()?->toArray());

            expect($data['columns']['pennies']['type'])->toBe('UiAmount')
                ->and($data['typeImports'])->toHaveKey('@js/types/ui')
                ->and($data['typeImports']['@js/types/ui'])->toContain('UiAmount')
                ->and($row->pennies)->toBeObject();
        });

        it('in a new-style accessor returning the class', function () use ($sent) {
            $data = new ModelTransformer(UiReading::class)->data()->toArray();
            $row = $sent(UiReading::query()->create(['pennies' => 5])->fresh()?->toArray());

            expect($data['appends']['badge']['type'])->toBe('UiIcon')
                ->and($data['typeImports']['@js/types/ui'] ?? [])->toContain('UiIcon')
                ->and($row->badge)->toBeObject();
        });
    });
});

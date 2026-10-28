<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\UiPanelPageController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UiPanelHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AnnouncingDraft;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AnnouncingPost;
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
        'type' => 'ChartType',
        'customImports' => [ComposedClassTypes::marker(Chart::class) => ['ChartType']],
        'contributors' => ClassTypeOverride::contributors(Chart::class),
        'composed' => true,
        'leftOut' => [],
    ])->and(ClassTypeOverride::resolve(Plain::class))->toBeNull()
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('lists a contributor reached twice once', function () {
    expect(array_column(ClassTypeOverride::contributors(Twice::class), 'type'))->toBe(['UiGlow', 'UiBase', 'UiIcon'])
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
            .'an interface cannot extend [string].',
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

it("keeps an owned class's own override alone", function () {
    expect(LaravelTsPublish::toTsType(AnnouncingPost::class))->toMatchArray([
        'type' => 'UiNode',
        'customImports' => ['@js/types/ui' => ['UiNode']],
    ])->and(ClassTypeOverride::resolve(AnnouncingPost::class))->toMatchArray(['composed' => false])
        ->and(array_column(ClassTypeOverride::resolve(AnnouncingPost::class)['leftOut'] ?? [], 'type'))->toBe(['UiHello'])
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => AnnouncingPost::class,
            'message' => 'Publishes the #[TsType] [UiNode] alone and leaves out [UiHello] from ['.AnnouncesHello::class.']: '
                .'a model, API resource, broadcast event, form request or enum publishes only its own.',
        ]]);
});

it("leaves out an owned class's inherited override when it has none of its own", function () {
    expect(ClassTypeOverride::resolve(AnnouncingDraft::class))->toBeNull()
        ->and(LaravelTsPublish::toTsType(AnnouncingDraft::class)['type'])->toBe('AnnouncingDraft')
        ->and(AnalysisWarnings::all())->toBe([[
            'subject' => AnnouncingDraft::class,
            'message' => 'Publishes no #[TsType] and leaves out [UiHello] from ['.AnnouncesHello::class.']: '
                .'a model, API resource, broadcast event, form request or enum publishes only its own.',
        ]]);
});

it('joins a composition only with an override that is one type reference', function (string $type, bool $extendable) {
    expect(ClassTypeOverride::extendable($type))->toBe($extendable);
})->with([
    'an identifier' => ['UiNode', true],
    'a dotted name' => ['Acme.Ui.Node', true],
    'a generic' => ['Record<string, Chart>', true],
    'a nested generic' => ['Map<string, Array<(a: string) => void>>', true],
    'a keyword' => ['string', false],
    'another keyword' => ['unknown', false],
    'a union' => ['UiNode | null', false],
    'a union of generics' => ['Foo<A> | Bar<B>', false],
    'an array' => ['UiNode[]', false],
    'an array of a generic' => ['Foo<A>[]', false],
    'a literal' => ["'a'", false],
    'an intersection' => ['A & B', false],
    'an object literal' => ['{ a: string }', false],
    'a type query' => ['typeof UiNode', false],
]);

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

        expect(array_map(fn (array $property): string => $property['type'], $transformer->properties))->toBe([
            'panel' => 'UiNode',
            'gauge' => 'UiGauge',
            'pennies' => 'UiAmount',
            'badge' => 'UiIcon',
            'maybe' => 'UiNode | null',
        ])->and($transformer->typeImports)->toBe(['@js/types/ui' => ['UiAmount', 'UiGauge', 'UiIcon', 'UiNode']])
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

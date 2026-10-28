<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;

beforeEach(fn () => ComposedClassTypes::flush());

afterEach(fn () => ComposedClassTypes::flush());

it('names a composed type after the class basename', function () {
    expect(ComposedClassTypes::typeName(Chart::class))->toBe('ChartType')
        ->and(ComposedClassTypes::typeName('\\'.Dial::class))->toBe('DialType');
});

it('carries a composed class in a marker no module specifier can be', function () {
    $marker = ComposedClassTypes::marker(Chart::class);

    expect($marker)->toBe('ts-publish:class-type:Workbench\\App\\Packages\\Ui\\Chart')
        ->and(ComposedClassTypes::marker('\\'.Chart::class))->toBe($marker)
        ->and(ComposedClassTypes::isMarker($marker))->toBeTrue()
        ->and(ComposedClassTypes::classOf($marker))->toBe(Chart::class);
});

it('reads no class from a module specifier', function (string $path) {
    expect(ComposedClassTypes::isMarker($path))->toBeFalse()
        ->and(ComposedClassTypes::classOf($path))->toBeNull();
})->with([
    'a package' => ['@js/types/ui'],
    'a relative path' => ['../packages/ui'],
    'an empty path' => [''],
]);

it('registers each composed class once, in the order values reach it', function () {
    ComposedClassTypes::register(Dial::class);
    ComposedClassTypes::register(Chart::class);
    ComposedClassTypes::register(Dial::class);

    expect(ComposedClassTypes::registered())->toBe([Dial::class, Chart::class]);

    ComposedClassTypes::flush();

    expect(ComposedClassTypes::registered())->toBe([]);
});

it('imports a composed class from its namespace directory, relative to the importing file', function (string $from, string $path) {
    expect(ComposedClassTypes::importPath(ComposedClassTypes::marker(Chart::class), $from))->toBe($path);
})->with([
    'a sibling namespace' => ['workbench/app/events', '../packages/ui'],
    'two levels up' => ['workbench/app/http/resources', '../../packages/ui'],
    'the output root' => ['', './workbench/app/packages/ui'],
    'its own namespace' => ['workbench/app/packages/ui', '.'],
]);

it('keeps a module specifier as it is', function (string $path) {
    expect(ComposedClassTypes::importPath($path, 'workbench/app/events'))->toBe($path);
})->with(['@js/types/ui', '../enums']);

it('resolves each marker in an import map, joining the names a path already has', function () {
    expect(ComposedClassTypes::resolveImports([
        '@js/types/ui' => ['UiNode'],
        '../packages/ui' => ['GaugeKind'],
        ComposedClassTypes::marker(Chart::class) => ['ChartType'],
        ComposedClassTypes::marker(Dial::class) => ['DialType', 'ChartType'],
    ], 'workbench/app/events'))->toBe([
        '@js/types/ui' => ['UiNode'],
        '../packages/ui' => ['GaugeKind', 'ChartType', 'DialType'],
    ]);
});

it('qualifies each composed type name by its class namespace for the globals file', function () {
    expect(ComposedClassTypes::globalReferences([Chart::class, Dial::class]))->toBe([
        'ChartType' => 'workbench.app.packages.ui.ChartType',
        'DialType' => 'workbench.app.packages.ui.DialType',
    ]);
});

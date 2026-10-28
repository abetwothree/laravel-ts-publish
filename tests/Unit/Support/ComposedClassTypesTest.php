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

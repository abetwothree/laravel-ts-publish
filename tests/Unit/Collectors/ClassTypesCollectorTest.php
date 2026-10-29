<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\ClassTypesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AnnouncingPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Gadget;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GadgetShade;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Greets;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\HiddenGreets;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\HiddenSpark;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Packages\Ui\Badge;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\HasIcon;

it('collects nothing unless a directory is configured', function () {
    config()->set('ts-publish.class_types.additional_directories', []);

    expect(resolve(ClassTypesCollector::class)->collect()->all())->toBe([]);
});

it('keeps only the classes whose override composes', function () {
    config()->set('ts-publish.class_types.additional_directories', [workbench_path('app/Packages/Ui')]);

    // The directory also holds an interface, a trait, single overrides, an unextendable one and a class with none.
    expect(resolve(ClassTypesCollector::class)->collect()->all())->toBe([Chart::class, Dial::class]);
});

it('skips a class another publisher owns and an enum', function () {
    config()->set('ts-publish.class_types.additional_directories', [AnnouncingPost::class, GadgetShade::class, Gadget::class]);

    expect(resolve(ClassTypesCollector::class)->collect()->all())->toBe([Gadget::class]);
});

it('collects an interface whose override composes, as it would a class', function () {
    config()->set('ts-publish.class_types.additional_directories', [Greets::class, HiddenGreets::class, HasIcon::class]);

    // HiddenGreets is #[TsExclude], and HasIcon's one override does not compose.
    expect(resolve(ClassTypesCollector::class)->collect()->all())->toBe([Greets::class]);
});

it('leaves interfaces out of every other collector\'s scan', function () {
    $collector = new class extends CoreCollector
    {
        /**
         * None: only the Ui package is scanned.
         */
        protected function defaultDirectory(): string
        {
            return '';
        }

        /**
         * Whatever implements HasIcon, which an interface extending it also does.
         *
         * @param  ReflectionClass<object>  $reflection
         */
        protected function classFilter(ReflectionClass $reflection): bool
        {
            return $reflection->implementsInterface(HasIcon::class);
        }

        /**
         * The Ui package's directory alone.
         *
         * @return array{included: list<string>, excluded: list<string>, additional_directories: list<string>}
         */
        protected function finderSettings(): array
        {
            return ['included' => [], 'excluded' => [], 'additional_directories' => [workbench_path('app/Packages/Ui')]];
        }
    };

    expect($collector->collect()->all())->toBe([Badge::class, Chart::class]);
});

it('asks no class filter about a #[TsExclude] class, so the scan warns of nothing', function () {
    config()->set('ts-publish.class_types.additional_directories', [HiddenSpark::class]);

    expect(resolve(ClassTypesCollector::class)->collect()->all())->toBe([])
        ->and(AnalysisWarnings::all())->toBe([]);
});

it('narrows the scan by included and excluded entries', function () {
    config()->set('ts-publish.class_types.additional_directories', [workbench_path('app/Packages/Ui'), Gadget::class]);
    config()->set('ts-publish.class_types.included', [Dial::class, Gadget::class]);
    config()->set('ts-publish.class_types.excluded', [Gadget::class]);

    expect(resolve(ClassTypesCollector::class)->collect()->all())->toBe([Dial::class]);
});

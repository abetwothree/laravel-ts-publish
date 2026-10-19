<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

test('resources collector finds resource classes', function () {
    $collector = resolve(ResourcesCollector::class);

    $resources = $collector->collect();

    expect($resources)
        ->toBeInstanceOf(Collection::class)
        ->toContain('Workbench\App\Http\Resources\PostResource')
        ->toContain('Workbench\App\Http\Resources\UserResource')
        ->toContain('Workbench\App\Http\Resources\CommentResource')
        ->toContain('Workbench\App\Http\Resources\OrderResource');
});

test('resources collector excludes EnumResource', function () {
    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)->not->toContain('AbeTwoThree\LaravelTsPublish\EnumResource');
});

test('resources collector respects included_resources config', function () {
    config()->set('ts-publish.resources.included', [
        'Workbench\App\Http\Resources\PostResource',
    ]);

    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)
        ->toHaveCount(1)
        ->toContain('Workbench\App\Http\Resources\PostResource');
});

test('resources collector respects excluded_resources config', function () {
    config()->set('ts-publish.resources.excluded', [
        ...config()->array('ts-publish.resources.excluded'),
        'Workbench\App\Http\Resources\PostResource',
    ]);

    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)
        ->not->toContain('Workbench\App\Http\Resources\PostResource')
        ->toContain('Workbench\App\Http\Resources\UserResource');
});

test('resources collector includes ResourceCollection subclasses', function () {
    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)
        ->toContain('Workbench\App\Http\Resources\UserCollection')
        ->toContain('Workbench\App\Http\Resources\OrderCollection');
});

// Loading a class whose parent is missing is fatal, so only an exclusion applied before the load leaves one out.
test('resources collector never loads an excluded class, so one whose parent is missing can be left out', function () {
    $directory = sys_get_temp_dir().'/ts-publish-test-'.uniqid();
    mkdir($directory, 0755, true);
    file_put_contents(
        $directory.'/OrphanResource.php',
        "<?php\n\nnamespace ProbeOrphans;\n\nclass OrphanResource extends \\ProbeOrphans\\MissingParent {}\n",
    );
    $autoload = function (string $class) use ($directory): void {
        if ($class === 'ProbeOrphans\OrphanResource') {
            require $directory.'/OrphanResource.php';
        }
    };
    spl_autoload_register($autoload);
    config()->set('ts-publish.resources.additional_directories', [$directory]);

    try {
        expect(fn () => resolve(ResourcesCollector::class)->collect())->toThrow(Error::class, 'MissingParent');

        config()->set('ts-publish.resources.excluded', [...config()->array('ts-publish.resources.excluded'), $directory]);

        expect(resolve(ResourcesCollector::class)->collect())
            ->not->toContain('ProbeOrphans\OrphanResource')
            ->toContain('Workbench\App\Http\Resources\PostResource');
    } finally {
        spl_autoload_unregister($autoload);
        (new Filesystem)->deleteDirectory($directory);
    }
});

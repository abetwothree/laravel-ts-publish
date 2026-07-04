<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\User;

beforeEach(fn () => DependencyRecorder::start());
afterEach(fn () => DependencyRecorder::stop());

it('records explicit dependency paths only while recording', function () {
    DependencyRecorder::record('/tmp/a.php');

    expect(DependencyRecorder::paths())->toContain('/tmp/a.php');

    DependencyRecorder::stop();
    DependencyRecorder::record('/tmp/b.php');

    expect(DependencyRecorder::paths())->not->toContain('/tmp/b.php');
});

it('collects the class file plus its parents, traits, and interfaces', function () {
    DependencyRecorder::recordClass(User::class);

    $paths = DependencyRecorder::paths();
    $userFile = (new ReflectionClass(User::class))->getFileName();

    expect($paths)->toContain($userFile)
        ->and($paths)->toContain((new ReflectionClass(Model::class))->getFileName());
});

it('de-duplicates recorded paths', function () {
    DependencyRecorder::record('/tmp/dup.php');
    DependencyRecorder::record('/tmp/dup.php');

    expect(array_count_values(DependencyRecorder::paths())['/tmp/dup.php'])->toBe(1);
});

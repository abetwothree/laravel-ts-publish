<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\OutputRecorder;
use AbeTwoThree\LaravelTsPublish\Writers\Concerns\WritesGeneratedFiles;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ts-publish-writes-'.uniqid();
    mkdir($this->dir, 0755, true);
    OutputRecorder::start();

    $this->writer = new class(new Filesystem)
    {
        use WritesGeneratedFiles;

        public function __construct(public Filesystem $filesystem) {}

        public function expose(string $path, string $content): void
        {
            $this->putIfChanged($path, $content);
        }
    };
});

afterEach(function () {
    OutputRecorder::stop();
    array_map('unlink', glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

it('writes a new file and records its path', function () {
    $path = $this->dir.'/a.ts';
    $this->writer->expose($path, 'hello');

    expect(file_get_contents($path))->toBe('hello')
        ->and(OutputRecorder::paths())->toContain($path);
});

it('does not rewrite an identical file (mtime preserved)', function () {
    $path = $this->dir.'/a.ts';
    $this->writer->expose($path, 'hello');
    $mtimeBefore = filemtime($path);

    usleep(1_100_000);
    $this->writer->expose($path, 'hello');

    expect(filemtime($path))->toBe($mtimeBefore);
});

it('rewrites when content differs', function () {
    $path = $this->dir.'/a.ts';
    $this->writer->expose($path, 'one');
    $this->writer->expose($path, 'two');

    expect(file_get_contents($path))->toBe('two');
});

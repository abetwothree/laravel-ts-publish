<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\FileCacheRepository;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ts-publish-cache-'.uniqid();
});

afterEach(function () {
    if (is_dir($this->dir)) {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
    }
});

it('stores and retrieves array payloads', function () {
    $repo = new FileCacheRepository($this->dir, null);
    $repo->put('alpha', ['n' => 1, 'list' => ['a', 'b']]);

    expect($repo->get('alpha'))->toBe(['n' => 1, 'list' => ['a', 'b']]);
});

it('returns null for missing keys', function () {
    expect((new FileCacheRepository($this->dir, null))->get('nope'))->toBeNull();
});

it('writes a self-ignoring .gitignore into the cache directory', function () {
    new FileCacheRepository($this->dir, null);

    expect(file_get_contents($this->dir.'/.gitignore'))->toContain('!.gitignore');
});

it('forgets and flushes keys', function () {
    $repo = new FileCacheRepository($this->dir, null);
    $repo->put('a', ['x' => 1]);
    $repo->put('b', ['x' => 2]);

    $repo->forget('a');
    expect($repo->get('a'))->toBeNull()->and($repo->get('b'))->toBe(['x' => 2]);

    $repo->flush();
    expect($repo->get('b'))->toBeNull();
});

it('rejects tampered payloads when a signing key is set', function () {
    $repo = new FileCacheRepository($this->dir, 'secret-key');
    $repo->put('signed', ['x' => 1]);

    $file = $this->dir.'/'.hash('xxh128', 'signed').'.cache';
    file_put_contents($file, 'deadbeef:'.serialize(['mtime' => 0]));

    expect($repo->get('signed'))->toBeNull();
});

it('tolerates concurrent processes racing to create the cache directory', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension is not available.');
    }

    // Regression test for a `mkdir(): File exists` crash: two processes
    // (e.g. parallel `npm run build` Vite builds each shelling out to `ts:publish`)
    // can both observe a missing cache directory
    $barrier = $this->dir.'.barrier';
    $pids = [];

    for ($i = 0; $i < 8; $i++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            test()->fail('Could not fork a child process.');
        }

        if ($pid === 0) {
            while (! is_file($barrier)) {
                usleep(500);
            }

            try {
                new FileCacheRepository($this->dir, null);
                exit(0);
            } catch (Throwable) {
                exit(1);
            }
        }

        $pids[] = $pid;
    }

    file_put_contents($barrier, '1');

    $failures = 0;

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $failures += pcntl_wexitstatus($status) === 0 ? 0 : 1;
    }

    @unlink($barrier);

    expect($failures)->toBe(0)
        ->and(is_dir($this->dir))->toBeTrue();
});

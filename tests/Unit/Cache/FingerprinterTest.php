<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Cache\Fingerprinter;

beforeEach(function () {
    $this->a = tempnam(sys_get_temp_dir(), 'fp');
    $this->b = tempnam(sys_get_temp_dir(), 'fp');
    file_put_contents($this->a, 'AAA');
    file_put_contents($this->b, 'BBB');
});

afterEach(function () {
    @unlink($this->a);
    @unlink($this->b);
});

it('is stable for the same file set and content', function () {
    expect(Fingerprinter::fromPaths([$this->a, $this->b]))
        ->toBe(Fingerprinter::fromPaths([$this->b, $this->a])); // order-independent
});

it('changes when a dependency file content changes', function () {
    $before = Fingerprinter::fromPaths([$this->a, $this->b]);
    file_put_contents($this->a, 'CHANGED');

    expect(Fingerprinter::fromPaths([$this->a, $this->b]))->not->toBe($before);
});

it('changes when a dependency is added', function () {
    $before = Fingerprinter::fromPaths([$this->a]);

    expect(Fingerprinter::fromPaths([$this->a, $this->b]))->not->toBe($before);
});

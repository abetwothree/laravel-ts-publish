<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Writers\BroadcastChannelsWriter;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.broadcast_channels.enabled', true);
    config()->set('ts-publish.broadcast_channels.output_path', '');
    config()->set('ts-publish.broadcast_channels.filename', 'broadcast-channels.ts');
    config()->set('ts-publish.broadcast_channels.template', 'laravel-ts-publish::broadcast-channels');
});

test('write returns TypeScript string with type and const exports', function () {
    $writer = resolve(BroadcastChannelsWriter::class);
    $content = $writer->write(collect(['orders.{orderId}', 'public-announcements']));

    expect($content)
        ->toContain('export type BroadcastChannel')
        ->toContain('export const BroadcastChannels');
});

test('write returns "export {};" for empty channel collection', function () {
    $writer = resolve(BroadcastChannelsWriter::class);
    $content = $writer->write(collect());

    expect($content)->toContain('export {};');
});

test('write does not write a file when output_to_files is false', function () {
    config()->set('ts-publish.output_to_files', false);

    $outputDir = sys_get_temp_dir().'/bc-writer-test-'.uniqid();
    config()->set('ts-publish.output_directory', $outputDir);

    $writer = resolve(BroadcastChannelsWriter::class);
    $writer->write(collect(['public-announcements']));

    expect(file_exists($outputDir.'/broadcast-channels.ts'))->toBeFalse();
});

test('write writes broadcast-channels.ts when output_to_files is true', function () {
    $outputDir = sys_get_temp_dir().'/bc-writer-test-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.output_directory', $outputDir);

    $writer = resolve(BroadcastChannelsWriter::class);
    $writer->write(collect(['orders.{orderId}', 'public-announcements']));

    $path = $outputDir.'/broadcast-channels.ts';

    expect(file_exists($path))->toBeTrue()
        ->and(file_get_contents($path))
        ->toContain('export type BroadcastChannel')
        ->toContain('export const BroadcastChannels');

    (new Filesystem)->deleteDirectory($outputDir);
});

test('write respects broadcast_channels.output_path config override', function () {
    $outputDir = sys_get_temp_dir().'/bc-writer-output-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.broadcast_channels.output_path', $outputDir);

    $writer = resolve(BroadcastChannelsWriter::class);
    $writer->write(collect(['public-announcements']));

    expect(file_exists($outputDir.'/broadcast-channels.ts'))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

test('write uses configured filename', function () {
    $outputDir = sys_get_temp_dir().'/bc-writer-filename-'.uniqid();
    config()->set('ts-publish.output_to_files', true);
    config()->set('ts-publish.output_directory', $outputDir);
    config()->set('ts-publish.broadcast_channels.filename', 'channels.ts');

    $writer = resolve(BroadcastChannelsWriter::class);
    $writer->write(collect(['public-announcements']));

    expect(file_exists($outputDir.'/channels.ts'))->toBeTrue();

    (new Filesystem)->deleteDirectory($outputDir);
});

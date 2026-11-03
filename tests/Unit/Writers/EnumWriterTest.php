<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Caseless;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\PaymentMethod;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\UiTone;
use Workbench\App\Enums\Visibility;
use Workbench\Shipping\Enums\Status as ShippingStatus;

test('writes enum content from transformer', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export const Status')
        ->toContain('Draft')
        ->toContain('Published')
        ->toContain('export type StatusType');
});

test('writes enum content with metadata enabled', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.metadata_enabled', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('_cases:')
        ->toContain('_methods:')
        ->toContain('_static:');
});

test('writes enum content with metadata disabled', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.metadata_enabled', false);

    $content = $writer->write($transformer);

    expect($content)
        ->not->toContain('_cases:')
        ->not->toContain('_methods:')
        ->not->toContain('_static:');
});

test('writes enum file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'status.ts') && str_contains($content, 'export const Status');
        });

    $writer = new EnumWriter($filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', true);

    $writer->write($transformer);
});

test('does not write enum file to disk when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('exists');
    $filesystem->shouldNotReceive('put');

    $writer = new EnumWriter($filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes backed enum Kind type', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)->toContain('export type StatusKind');
});

test('omits _methods and _static when enum has no methods or static methods', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(PaymentMethod::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.metadata_enabled', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('_cases:')
        ->not->toContain('_methods:')
        ->not->toContain('_static:');
});

test('includes _methods when enum has instance methods', function () {
    $writer = new EnumWriter(new Filesystem);
    $transformer = new EnumTransformer(Status::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.enums.metadata_enabled', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('_cases:')
        ->toContain('_methods:')
        ->toContain('_static:');
});

describe('the enum\'s own name as a type', function () {
    beforeEach(fn () => config()->set('ts-publish.output_to_files', false));

    test('publishes the value union under the enum\'s own name in every variant', function (bool $metadata, bool $tolki) {
        config()->set('ts-publish.enums.metadata_enabled', $metadata);
        config()->set('ts-publish.enums.use_tolki_package', $tolki);

        $content = (new EnumWriter(new Filesystem))->write(new EnumTransformer(Status::class));

        expect($content)->toEndWith("export type StatusKind = 'Draft' | 'Published';\n\nexport type Status = StatusType;\n")
            ->and(substr_count($content, 'export type Status ='))->toBe(1);
    })->with([[true, true], [true, false], [false, true], [false, false]]);

    test('a renamed enum publishes the alias under its new name', fn () => expect(
        (new EnumWriter(new Filesystem))->write(new EnumTransformer(ShippingStatus::class)),
    )->toContain('export type ShipmentStatus = ShipmentStatusType;'));

    test('a unit enum\'s alias is the union of its case names', fn () => expect(
        (new EnumWriter(new Filesystem))->write(new EnumTransformer(Visibility::class)),
    )->toContain('export type Visibility = VisibilityType;')->not->toContain('VisibilityKind'));

    test('an overridden enum\'s alias names its own union, not the override', fn () => expect(
        (new EnumWriter(new Filesystem))->write(new EnumTransformer(UiTone::class)),
    )->toContain('export type UiTone = UiToneType;'));

    test('a caseless enum publishes never for its value and kind unions', fn () => expect(
        (new EnumWriter(new Filesystem))->write(new EnumTransformer(Caseless::class)),
    )->toContain('export type CaselessType = never;')
        ->toContain('export type CaselessKind = never;')
        ->toContain('export type Caseless = CaselessType;'));
});

describe('a renamed case\'s instance-method values', function () {
    beforeEach(fn () => config()->set('ts-publish.output_to_files', false));

    test('are keyed by the case name the enum publishes', fn () => expect(
        (new EnumWriter(new Filesystem))->write(new EnumTransformer(Color::class)),
    )->toContain("        Yellow: '#F59E0B',")
        ->not->toContain('Amber:')
        ->not->toContain('Gray:'));
});

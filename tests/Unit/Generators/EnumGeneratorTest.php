<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\EnumWriter;
use Workbench\App\Enums\FreightClass;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

test('generates enum typescript content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->content)
        ->toContain('export const Status')
        ->toContain('Draft')
        ->toContain('Published');
});

test('exposes transformer property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->transformer)->toBeInstanceOf(EnumTransformer::class)
        ->and($generator->transformer->enumName)->toBe('Status');
});

test('exposes findable property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->findable)->toBe(Status::class);
});

test('filename delegates to transformer', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->filename())->toBe('status');
});

test('generates unit enum content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Role::class]);

    expect($generator->content)
        ->toContain('export const Role')
        ->toContain("'Admin'")
        ->toContain("'User'");
});

test('an enum whose methods return objects renders byte-identical twice and from its cached snapshot', function () {
    config()->set('ts-publish.output_to_files', false);

    $first = resolve(EnumGenerator::class, ['findable' => FreightClass::class]);
    $second = resolve(EnumGenerator::class, ['findable' => FreightClass::class]);
    $restored = resolve(EnumWriter::class)->write(unserialize(serialize($first->transformer)));

    expect($first->content)
        ->toContain("        Standard: '2026-01-01T17:00:00.000000Z',")
        ->not->toContain('constructedObjectId')
        ->and($second->content)->toBe($first->content)
        ->and($restored)->toBe($first->content);
});

test('an enum transformer holds each method value as json_encode() writes it', function () {
    config()->set('ts-publish.output_to_files', false);

    $transformer = resolve(EnumGenerator::class, ['findable' => FreightClass::class])->transformer;

    expect($transformer->methods['cutoff']['returns'])
        ->toBe(['Standard' => '2026-01-01T17:00:00.000000Z', 'Express' => '2026-01-01T20:00:00.000000Z'])
        ->and($transformer->methods['manifest']['returns']['Standard'])->toEqual(new stdClass)
        ->and($transformer->staticMethods['defaultRate']['return'])->toBe(['amount' => 50]);
});

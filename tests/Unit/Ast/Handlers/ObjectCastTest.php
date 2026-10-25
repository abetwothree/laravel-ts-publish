<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CastObjectResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\ReshapedListsPayload;

it('types an (object) cast as the object it encodes, and stdClass as a record', function () {
    expect(analyzedKeyTypes(ReshapedListsPayload::class))->toMatchArray([
        'counts_object' => 'Record<string, number>',
        'tags_object' => 'Record<string, string>',
        'size_object' => '{ width: number; label: string }',
        'empty_object' => 'Record<string, never>',
        'scalar_object' => '{ scalar: string }',
        'counts_array' => 'Record<string, number>',
        'blank' => 'Record<string, unknown>',
        'fresh' => 'Record<string, unknown>',
    ])->and(json_encode(new ReshapedListsPayload()->toArray()))->toContain(
        '"counts_object":{"a":1},"tags_object":{"0":"a"},"size_object":{"width":1,"label":"x"},"empty_object":{},'
        .'"scalar_object":{"scalar":"x"},"counts_array":{"a":1}',
    );
});

it('leaves an object the cast returns unchanged', function () {
    expect(analyzedKeyTypes(ReshapedListsPayload::class)['collection_object'])->toBe('string[]');
});

it('keeps the numeric keys of a cast object in a resource', function () {
    expect(publishedResourceKeyTypes(CastObjectResource::class, Post::class))->toBe([
        'codes' => '{ "10": string; "20": string }',
        'list' => 'string[]',
    ])->and((new CastObjectResource(new Post))->resolve(request()))
        ->toEqual(['codes' => (object) [10 => 'a', 20 => 'b'], 'list' => ['a', 'b']]);
});

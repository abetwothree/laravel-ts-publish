<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\SerializingAttributesApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\SummarizedPostApiResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Http\Resources\JsonApi\AttributesPostResource;
use Workbench\App\Http\Resources\JsonApi\BarePostResource;
use Workbench\App\Http\Resources\JsonApi\CastedAttributesPostResource;
use Workbench\App\Http\Resources\JsonApi\ClosureRelationsPostResource;
use Workbench\App\Http\Resources\JsonApi\DefaultLinksPostResource;
use Workbench\App\Http\Resources\JsonApi\IdentifiedPostResource;
use Workbench\App\Http\Resources\JsonApi\LinkedPostResource;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Http\Resources\JsonApi\ToArrayPostResource;
use Workbench\App\Http\Resources\JsonApi\ToAttributesOnlyPlainResource;
use Workbench\App\Models\Post;

beforeEach(function () {
    // JsonApiResource and JsonResource::toAttributes() ship from Laravel 12.45; an older install cannot load these.
    if (! class_exists('Illuminate\Http\Resources\JsonApi\JsonApiResource')) {
        $this->markTestSkipped('JsonApiResource ships from Laravel 12.45.');
    }
});

/** The `attributes` member PostResource publishes. */
function postResourceAttributes(): string
{
    return '{ title: string; content: string; word_count: number | null; published_at: string | null }';
}

/** The `relationships` member PostResource publishes. */
function postResourceRelationships(): string
{
    return "{ author?: JsonApiToOneRelationship<'users'>; comments?: JsonApiToManyRelationship<'comments'>; categoryRel?: JsonApiToOneRelationship<'categories'> }";
}

test('a body-less JsonApiResource publishes the resource object its $attributes and $relationships send', function () {
    expect(publishedResourceKeyTypes(PostResource::class, Post::class))->toBe([
        'id' => 'string',
        'type' => "'posts'",
        'attributes' => postResourceAttributes(),
        'relationships?' => postResourceRelationships(),
    ]);
});

test('a toAttributes() override types the attributes, a when() key optional', function () {
    expect(publishedResourceKeyTypes(AttributesPostResource::class, Post::class))->toBe([
        'id' => 'string',
        'type' => "'attributes_posts'",
        'attributes' => '{ title: string; word_count: number | null; is_long: boolean; secret?: string }',
    ]);
});

test('a declared toArray() publishes as the attributes toAttributes() falls back to', function () {
    expect(publishedResourceKeyTypes(ToArrayPostResource::class, Post::class))->toBe([
        'id' => 'string',
        'type' => "'to_array_posts'",
        'attributes' => '{ title: string; rating: string | null }',
    ]);
});

test('a JsonApiResource naming no attributes publishes the whole model under attributes', function () {
    expect(publishedResourceKeyTypes(BarePostResource::class, Post::class)['attributes'])
        ->toStartWith('{ id: number; title: string; content: string; user_id: number; status: StatusType;')
        ->toContain('; metadata: Record<string, {title: string, content: string}>;')
        ->toEndWith('author?: User; category_rel?: Category | null; comments?: Comment[]; tags?: Tag[]; images?: Image[]; attachment?: Attachment | null }');
});

test('a toType() literal publishes as the type, and an int toId() as string', function () {
    expect(publishedResourceKeyTypes(IdentifiedPostResource::class, Post::class))->toBe([
        'id' => 'string',
        'type' => "'articles'",
        'attributes' => '{ title: string }',
    ]);
});

test('toLinks() and toMeta() overrides publish optional links and meta', function () {
    expect(publishedResourceKeyTypes(LinkedPostResource::class, Post::class))
        ->toMatchArray(['links?' => '{ self: string }', 'meta?' => '{ readable: boolean; words: number | null }']);
});

test('a toAttributes() list name reads the model attribute, and toRelationships() closures name their resources', function () {
    expect(publishedResourceKeyTypes(ClosureRelationsPostResource::class, Post::class))->toBe([
        'id' => 'string',
        'type' => "'closure_relations_posts'",
        'attributes' => '{ title: string; published_at: string | null; excerpt: string | null; is_published: boolean }',
        'relationships?' => "{ author?: JsonApiToOneRelationship<'users'>; comments?: JsonApiToManyRelationship<'comments'> }",
    ]);
});

test('a JSON:API resource file imports the relationship generics from @tolki/types', function () {
    config()->set('ts-publish.output_to_files', false);

    expect(new ResourceWriter(new Filesystem)->write(new ResourceTransformer(PostResource::class)))
        ->toContain("import type { JsonApiToManyRelationship, JsonApiToOneRelationship } from '@tolki/types';")
        ->toContain("    type: 'posts';")
        ->toContain('    attributes: '.postResourceAttributes().';')
        ->toContain('    relationships?: '.postResourceRelationships().';');
});

test('a model #[TsCasts] key lays over the attributes member', function () {
    expect(new ResourceTransformer(CastedAttributesPostResource::class)->data()->properties)->toBe([
        'id' => ['type' => 'string', 'optional' => false, 'description' => ''],
        'type' => ['type' => "'casted_attributes_posts'", 'optional' => false, 'description' => ''],
        'attributes' => [
            'type' => '{ metadata: Record<string, {title: string, content: string}>; rating: `${number}` | null; status: StatusType }',
            'optional' => false,
            'description' => '',
        ],
    ]);
});

test('the $jsonApiLinks and $jsonApiMeta defaults publish', function () {
    expect(publishedResourceKeyTypes(DefaultLinksPostResource::class, Post::class))
        ->toMatchArray(['links?' => '{ describedby: string }', 'meta?' => '{ version: number; stable: boolean }']);
});

test('a plain resource declaring only toAttributes() publishes its keys', function () {
    expect(publishedResourceKeyTypes(ToAttributesOnlyPlainResource::class, Post::class))->toBe(['headline' => 'string']);
});

test('analyze() answers the resource object', function () {
    $result = resolve(AstEngine::class)->analyze(PostResource::class);

    expect(array_column($result->properties, 'type', 'name'))->toBe([
        'id' => 'string',
        'type' => "'posts'",
        'attributes' => postResourceAttributes(),
        'relationships' => postResourceRelationships(),
    ])->and($result->typeImports)->toBe(['@tolki/types' => ['JsonApiToManyRelationship', 'JsonApiToOneRelationship']]);
});

test('a JSON:API resource\'s resolve() and jsonSerialize() send its resource object under data', function (string $method) {
    $object = "{ id: string; type: 'posts'; attributes: ".postResourceAttributes().'; relationships?: '.postResourceRelationships().' }';

    expect(array_column(resolve(AstEngine::class)->analyze(PostResource::class, $method)->properties, 'type', 'name'))
        ->toBe(['data' => $object]);
})->with(['resolve', 'jsonSerialize']);

test('an ancestor\'s jsonSerialize() read builds the runtime class\'s resource object', function () {
    expect(array_column(resolve(AstEngine::class)->analyze(SummarizedPostApiResource::class, 'summary')->properties, 'type', 'name'))
        ->toBe(['data' => "{ id: string; type: 'summarized_posts'; attributes: { title: string } }"]);
});

test('a parent::jsonSerialize() that builds the resource object it is read for adds no key', function () {
    expect(publishedResourceKeyTypes(SerializingAttributesApiResource::class, Post::class))->toBe([
        'id' => 'string',
        'type' => "'serializing_attributes_apis'",
        'attributes' => '{ headline: string }',
    ]);
});

<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\ConstantResolverApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\JsonApiValueReadsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\KeylessToArrayApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\LoopingOverrideApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\LoopingToArrayApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\ParentToArrayReturnApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\ParentToArraySpreadApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\ParentToAttributesSpreadApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\PlainRelationshipApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\ResolveSpreadResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\SerializingAttributesApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\SummarizedPostApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\ToAttributesBesideToArrayResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Controllers\InertiaJsonApiController;
use Workbench\App\Http\Resources\JsonApi\AttributesPostResource;
use Workbench\App\Http\Resources\JsonApi\BarePostResource;
use Workbench\App\Http\Resources\JsonApi\CastedAttributesPostResource;
use Workbench\App\Http\Resources\JsonApi\ClosureRelationsPostResource;
use Workbench\App\Http\Resources\JsonApi\CommentResource;
use Workbench\App\Http\Resources\JsonApi\DefaultLinksPostResource;
use Workbench\App\Http\Resources\JsonApi\IdentifiedPostResource;
use Workbench\App\Http\Resources\JsonApi\KpiResource;
use Workbench\App\Http\Resources\JsonApi\LinkedPostResource;
use Workbench\App\Http\Resources\JsonApi\NestingPlainResource;
use Workbench\App\Http\Resources\JsonApi\PostCollection;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Http\Resources\JsonApi\ToArrayPostResource;
use Workbench\App\Http\Resources\JsonApi\ToAttributesOnlyPlainResource;
use Workbench\App\Http\Resources\JsonApi\VariableAttributesPostResource;
use Workbench\App\Models\Kpi;
use Workbench\App\Models\Marketing\Report\Report as MarketingReport;
use Workbench\App\Models\Post;
use Workbench\App\Models\Sales\Report\Report as SalesReport;

beforeEach(function () {
    // JsonApiResource and JsonResource::toAttributes() ship from Laravel 12.45; an older install cannot load these.
    if (! class_exists('Illuminate\Http\Resources\JsonApi\JsonApiResource')) {
        $this->markTestSkipped('JsonApiResource ships from Laravel 12.45.');
    }
});

afterEach(function () {
    JsonResource::flushState();
    Relation::morphMap([], merge: false);
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

/** Read each MorphTo's targets off the reports' inverse relations, as a publish run does. */
function buildReportMorphTargets(): void
{
    resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Kpi::class, MarketingReport::class, SalesReport::class]);
}

/** The props one InertiaJsonApiController action publishes. */
function jsonApiPageType(string $action): string
{
    return new InertiaPageAnalyzer()->analyze(['uses' => InertiaJsonApiController::class.'@'.$action])['pageType'];
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

// JsonApiResource declares no toArray(), so parent::toArray() is JsonResource's, which sends the model's own array.
test('a toArray() that builds on parent::toArray() sends the whole model under attributes', function (string $resource, string $added) {
    $model = publishedResourceKeyTypes(BarePostResource::class, Post::class)['attributes'];

    expect(publishedResourceKeyTypes($resource, Post::class))->toMatchArray([
        'attributes' => substr($model, 0, -2).$added.' }',
    ]);
})->with([
    'a spread beside a key' => [ParentToArraySpreadApiResource::class, '; extra: number'],
    'a whole return' => [ParentToArrayReturnApiResource::class, ''],
]);

test('a parent::toAttributes() spread reads the $attributes names JsonApiResource returns', function () {
    expect(publishedResourceKeyTypes(ParentToAttributesSpreadApiResource::class, Post::class))
        ->toMatchArray(['attributes' => '{ title: string; extra: number }']);
});

test('a keyless name an own toArray() returns reads the model attribute', function () {
    expect(publishedResourceKeyTypes(KeylessToArrayApiResource::class, Post::class))
        ->toMatchArray(['attributes' => '{ title: string; content: string }']);
});

test('a plain resource declaring toAttributes() beside toArray() publishes toAttributes()', function () {
    expect(publishedResourceKeyTypes(ToAttributesBesideToArrayResource::class, Post::class))->toBe(['headline' => 'string']);
});

// Only `X::class` names a resource; a constant's value is not read, so the related model's guess stands.
test('a class constant resolver names no resource', function () {
    expect(publishedResourceKeyTypes(ConstantResolverApiResource::class, Post::class))
        ->toMatchArray(['relationships?' => '{ author?: JsonApiToOneRelationship<string> }']);
});

test('a toArray() that spreads parent::toAttributes() with no $attributes ends, though Laravel never returns from it', function (string $resource, string $attributes) {
    expect(publishedResourceKeyTypes($resource, Post::class))->toMatchArray(['attributes' => $attributes]);
})->with([
    'from toArray()' => [LoopingToArrayApiResource::class, '{ extra: number }'],
    'from a toAttributes() override toArray() spreads' => [LoopingOverrideApiResource::class, '{ headline: string; more: number }'],
]);

test('a JSON:API resource nested in a plain resource publishes { data: R }, and its collection() R[]', function () {
    expect(publishedResourceKeyTypes(NestingPlainResource::class, Post::class))->toMatchArray([
        'author' => '{ data: UserResource }',
        'comments' => 'CommentResource[]',
    ]);
});

test('toResource() of a JSON:API resource is { data: R }', function () {
    expect(publishedResourceKeyTypes(NestingPlainResource::class, Post::class)['category'])->toBe('{ data: CategoryResource } | null');
});

test('a named collection of JSON:API resources publishes each item under data', function () {
    expect(new ResourceTransformer(PostCollection::class)->data()->properties['data']['type'])
        ->toBe('{ data: PostResource }[]')
        ->and(publishedResourceKeyTypes(NestingPlainResource::class, Post::class)['siblings'])
        ->toBe('{ data: PostResource }[]');
});

test('an Inertia prop publishes the JSON:API document, with every resource an include can add', function () {
    $included = 'UserResource | CommentResource | CategoryResource';

    expect(jsonApiPageType('show'))
        ->toContain("post: JsonApiDocument<PostResource, {$included}>")
        ->toContain('author: JsonApiDocument<UserResource, never>')
        ->toContain('category: JsonApiDocument<CategoryResource, never> | null')
        ->and(jsonApiPageType('index'))
        ->toContain("posts: JsonApiCollection<PostResource, {$included}>")
        ->toContain("paged: JsonApiPaginator<PostResource, {$included}>")
        ->toContain("simple: JsonApiSimplePaginator<PostResource, {$included}>")
        ->toContain("cursor: JsonApiCursorPaginator<PostResource, {$included}>")
        ->toContain('comments: JsonApiCollection<CommentResource, UserResource>');
});

test('a relationship without a JSON:API resource adds JsonApiResourceObject to included', function () {
    buildReportMorphTargets();

    expect(jsonApiPageType('show'))->toContain("kpi: JsonApiDocument<KpiResource, JsonApiResourceObject<'reports'>>");
});

test('a JSON:API collection prop after withoutWrapping() is the bare list', function () {
    JsonResource::withoutWrapping();

    expect(jsonApiPageType('index'))
        ->toContain('posts: PostResource[]')
        ->toContain('paged: JsonApiPaginator<PostResource, UserResource | CommentResource | CategoryResource>');
});

test('keyless names in a toAttributes() that returns a variable read the model attributes', function () {
    expect(publishedResourceKeyTypes(VariableAttributesPostResource::class, Post::class))
        ->toMatchArray(['attributes' => '{ title: string; word_count: number | null; content?: string; is_long: boolean }']);
});

test('a morph relationship publishes each target\'s type literal', function () {
    buildReportMorphTargets();
    Relation::morphMap(['marketing_report' => MarketingReport::class, 'sales_report' => SalesReport::class]);

    expect(publishedResourceKeyTypes(KpiResource::class, Kpi::class)['relationships?'])
        ->toBe("{ reportable?: JsonApiToOneRelationship<'marketing_reports' | 'sales_reports'> }")
        ->and(JsonApiResources::includedResources(KpiResource::class))->toBe([
            ['type' => "'marketing_reports'", 'resource' => null],
            ['type' => "'sales_reports'", 'resource' => null],
        ]);
});

// Laravel throws building a plain resource's identifier, and builds a bare JsonApiResource over a model with none.
test('a relationship to a plain resource or to a model with no resource publishes a type that compiles', function (string $resource, string $model, string $relationships, array $included) {
    buildReportMorphTargets();

    expect(publishedResourceKeyTypes($resource, $model)['relationships?'])->toBe($relationships)
        ->and(JsonApiResources::includedResources($resource))->toBe($included);
})->with([
    'a plain resource' => [
        PlainRelationshipApiResource::class,
        Post::class,
        '{ author?: JsonApiToOneRelationship<string>; comments?: JsonApiToManyRelationship<string> }',
        [],
    ],
    'no resource' => [
        KpiResource::class,
        Kpi::class,
        "{ reportable?: JsonApiToOneRelationship<'reports'> }",
        [['type' => "'reports'", 'resource' => null]],
    ],
]);

// D8: only the published subject is the resource object; a call reads what the method returns.
test('a call to a JSON:API resource\'s toArray() reads the method, and its resolve() the resource object under data', function () {
    expect(publishedResourceKeyTypes(JsonApiValueReadsResource::class, Post::class))->toBe([
        'declared' => '{ title: string; rating: string | null }',
        'made' => '{ data: PostResource }',
        'built' => '{ data: PostResource }',
    ]);
});

test('a spread of a JSON:API resource\'s resolve() adds its data key', function () {
    expect(publishedResourceKeyTypes(ResolveSpreadResource::class, Post::class))->toBe([
        'id' => 'number',
        'data' => 'PostResource',
    ]);
});

// A run reads the morph map as the app booted it, so an answer worked out before this change is the one a run reuses.
test('a JSON:API resource\'s relationships and includes are read once a run, and again after a reset', function () {
    buildReportMorphTargets();
    $relationships = JsonApiResources::relationships(KpiResource::class, Kpi::class);
    $included = JsonApiResources::includedResources(KpiResource::class);

    Relation::morphMap(['marketing_report' => MarketingReport::class, 'sales_report' => SalesReport::class]);
    $reused = [JsonApiResources::relationships(KpiResource::class, Kpi::class), JsonApiResources::includedResources(KpiResource::class)];
    resolve(AnalysisMemo::class)->reset();

    expect($included)->toBe([['type' => "'reports'", 'resource' => null]])
        ->and($reused)->toBe([$relationships, $included])
        ->and(JsonApiResources::includedResources(KpiResource::class))->toBe([
            ['type' => "'marketing_reports'", 'resource' => null],
            ['type' => "'sales_reports'", 'resource' => null],
        ]);
});

test('a reused JSON:API include read records the file of every resource it reached', function () {
    $recorded = function (Closure $read): array {
        DependencyRecorder::start();

        try {
            $read();

            return DependencyRecorder::paths();
        } finally {
            DependencyRecorder::stop();
        }
    };

    $first = $recorded(fn () => JsonApiResources::includedResources(PostResource::class));
    $reused = $recorded(fn () => JsonApiResources::includedResources(PostResource::class));

    expect($first)->toContain((string) new ReflectionClass(CommentResource::class)->getFileName())
        ->and($reused)->toEqualCanonicalizing($first);
});

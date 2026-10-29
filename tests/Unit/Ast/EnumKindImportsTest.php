<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\SameNamedEnumNamesController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumWrapOrNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InlineEnumKindResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\InlineSameNamedKindsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\EnumNamePost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\EnumNameMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\SameNamedEnumNamesMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\Status;
use Workbench\App\Events\PostStatusBroadcast;
use Workbench\App\Http\Controllers\InertiaEnumCaseController;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\EnumValuesPayload;
use Workbench\App\ValueObjects\SameNamedKindsPayload;

beforeEach(function () {
    PublishedEnums::flush();
});

it('aliases two same-named enums\' Kind names as it aliases their Type names', function () {
    $result = resolve(AstEngine::class)->analyze(SameNamedKindsPayload::class);

    expect(analyzedKeyTypes(SameNamedKindsPayload::class))->toBe([
        'status' => 'WorkbenchStatusType', 'status_name' => 'WorkbenchStatusKind',
        'crm_status' => 'CrmStatusType', 'crm_status_name' => 'CrmStatusKind',
    ])->and(array_merge(...array_values($result->typeImports)))->toEqualCanonicalizing([
        'StatusKind as CrmStatusKind', 'StatusKind as WorkbenchStatusKind',
        'StatusType as CrmStatusType', 'StatusType as WorkbenchStatusType',
    ]);
});

it('sends the values and names the same-named payload types admit', function () {
    $payload = new SameNamedKindsPayload;

    expect(json_encode($payload->toArray()))
        ->toBe('{"status":0,"status_name":"Draft","crm_status":"lead","crm_status_name":"Lead"}')
        ->and(json_encode($payload->unions()))->toBe('{"value_or_name":0,"name_or_name":"Draft"}');
});

it('imports no Kind name a type does not spell', function () {
    config()->set('ts-publish.enums.excluded', [Grade::class]);

    expect(analyzedImportNames(EnumValuesPayload::class))->not->toContain('GradeKind')
        ->and(analyzedImportNames(EnumValuesPayload::class))->toContain('PriorityKind');
});

it('gives a Type token and its same-named sibling\'s Kind token each its own prefix', function () {
    expect(analyzedKeyTypes(SameNamedKindsPayload::class, 'unions'))->toBe([
        'value_or_name' => 'WorkbenchStatusType | CrmStatusKind',
        'name_or_name' => 'WorkbenchStatusKind | CrmStatusKind',
    ])->and(analyzedImportNames(SameNamedKindsPayload::class, 'unions'))->toBe([
        'StatusKind as CrmStatusKind', 'StatusKind as WorkbenchStatusKind', 'StatusType as WorkbenchStatusType',
    ]);
});

it('keeps both arms of a ternary that wraps an enum or reads its name, each with its import', function () {
    $resource = (new ResourceTransformer(EnumWrapOrNameResource::class))->data();
    $analysis = resolve(AstEngine::class)->analyze(EnumWrapOrNameResource::class, 'toArray', Post::class);

    expect($resource->properties['status']['type'])->toBe('AsEnum<typeof Status> | StatusKind')
        ->and(array_merge(...array_values($resource->typeImports)))->toBe(['StatusKind'])
        ->and(array_merge(...array_values($resource->valueImports)))->toBe(['Status'])
        ->and(array_column($analysis->properties, 'type', 'name'))->toBe(['status' => 'AsEnum<typeof Status> | StatusKind'])
        ->and(array_merge(...array_values($analysis->typeImports)))->toBe(['StatusKind'])
        ->and(array_merge(...array_values($analysis->valueImports)))->toBe(['Status']);
});

it('imports the Kind a nested array\'s member spells, its enum\'s only token', function () {
    $resource = (new ResourceTransformer(InlineEnumKindResource::class))->data();
    $analysis = resolve(AstEngine::class)->analyze(InlineEnumKindResource::class, 'toArray', Post::class);

    expect($resource->properties['meta']['type'])->toBe('{ name: StatusKind }')
        ->and(array_merge(...array_values($resource->typeImports)))->toBe(['StatusKind'])
        ->and(array_column($analysis->properties, 'type', 'name'))->toBe(['meta' => '{ name: StatusKind }'])
        ->and(array_merge(...array_values($analysis->typeImports)))->toBe(['StatusKind'])
        ->and(new InlineEnumKindResource((new Post)->forceFill(['status' => 0]))->resolve(request()))
        ->toBe(['meta' => ['name' => 'Draft']]);
});

it('aliases two same-named enums\' Kind names inside one nested array', function () {
    $resource = (new ResourceTransformer(InlineSameNamedKindsResource::class))->data();
    $aliased = ['StatusKind as CrmStatusKind', 'StatusKind as WorkbenchStatusKind'];

    expect(analyzedKeyTypes(SameNamedKindsPayload::class, 'inner'))
        ->toBe(['inner' => '{ a: WorkbenchStatusKind; b: CrmStatusKind }'])
        ->and(analyzedImportNames(SameNamedKindsPayload::class, 'inner'))->toBe($aliased)
        ->and($resource->properties['inner']['type'])->toBe('{ a: WorkbenchStatusKind; b: CrmStatusKind }')
        ->and(array_merge(...array_values($resource->typeImports)))->toEqualCanonicalizing($aliased)
        ->and(json_encode(new SameNamedKindsPayload()->inner()))->toBe('{"inner":{"a":"Draft","b":"Lead"}}')
        ->and(new InlineSameNamedKindsResource((new Post)->forceFill(['status' => 0]))->resolve(request()))
        ->toBe(['inner' => ['a' => 'Draft', 'b' => 'Lead']]);
});

it('sends the wrap or the case name the ternary types admit', function () {
    $unpinned = (new Post)->forceFill(['is_pinned' => false, 'status' => 0]);
    $pinned = (new Post)->forceFill(['is_pinned' => true, 'status' => 1]);

    expect(new EnumWrapOrNameResource($unpinned)->resolve(request()))->toBe(['status' => 'Draft'])
        ->and(new EnumWrapOrNameResource($pinned)->resolve(request())['status'])
        ->toBeInstanceOf(EnumResource::class);
});

it('imports the Kind a page prop reads beside the Type', function () {
    expect(new InertiaPageAnalyzer()->analyze(['uses' => InertiaEnumCaseController::class.'@show'])['pageType'])
        ->toContain('status_name: StatusKind')
        ->and((new RouteTransformer(InertiaEnumCaseController::class))->typeImports['../../enums'])
        ->toBe(['StatusKind', 'StatusType']);
});

it('aliases the Kind an event key reads as its Type is aliased beside a same-named enum', function () {
    $transformer = app(BroadcastEventTransformer::class, ['findable' => PostStatusBroadcast::class]);
    $post = (new Post)->forceFill(['status' => 0]);

    expect($transformer->properties['status_name']['type'])->toBe('AppStatusKind')
        ->and(array_merge(...array_values($transformer->typeImports)))->toContain('StatusKind as AppStatusKind')
        ->and(new PostStatusBroadcast($post)->broadcastWith()['status_name'])->toBe(Status::Draft->name);
});

it('imports the Kind a metadata provider key reads', function () {
    config()->set('ts-publish.model_metadata.provider_class', EnumNameMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->properties)->toBe(['status' => 0, 'status_name' => 'Draft'])
        ->and($data->propertyTypes)->toBe(['status' => 'StatusType', 'status_name' => 'StatusKind'])
        ->and($data->typeImports)->toBe(['../enums' => ['StatusKind', 'StatusType']]);
});

it('imports the Kind a model accessor reads beside the Type', function () {
    config()->set('ts-publish.output_to_files', false);

    $transformer = new ModelTransformer(EnumNamePost::class);
    $post = (new EnumNamePost)->forceFill(['is_pinned' => false, 'status' => 0]);

    expect($transformer->appends['status_name']['type'])->toBe('StatusKind')
        ->and($transformer->appends['status_both']['type'])->toBe('StatusType | StatusKind')
        ->and(new ModelWriter(new Filesystem)->write($transformer))
        ->toMatch("/^import type \\{ [^}]*\\bStatusKind, StatusType\\b[^}]* \\} from '[^']+';$/m")
        ->and([$post->status_name, $post->status_both])->toBe(['Draft', 'Draft']);
});

it('spells two same-named enums\' case names as literals in a route file, which aliases no import', function () {
    $router = app(Router::class);
    $router->get('/same-named/both', [SameNamedEnumNamesController::class, 'both']);
    $router->get('/same-named/app', [SameNamedEnumNamesController::class, 'app']);
    $router->get('/same-named/crm', [SameNamedEnumNamesController::class, 'crm']);

    $transformer = new RouteTransformer(SameNamedEnumNamesController::class);
    $pageTypes = array_column($transformer->actions, 'pageType', 'methodName');
    $crmNames = "'Lead' | 'Prospect' | 'Active' | 'Churned'";

    expect($pageTypes)->toBe([
        'both' => "Inertia.SharedData & { app_name: 'Draft' | 'Published', crm_name: {$crmNames}, priority_name: PriorityKind }",
        'app' => "Inertia.SharedData & { name: 'Draft' | 'Published' }",
        'crm' => "Inertia.SharedData & { name: {$crmNames} }",
    ])->and(array_merge(...array_values($transformer->typeImports)))->toBe(['PriorityKind']);
});

it('spells two same-named enums\' case names as literals in a metadata companion, which aliases no import', function () {
    config()->set('ts-publish.model_metadata.provider_class', SameNamedEnumNamesMetadataProvider::class);

    $data = (new ModelMetadataTransformer(User::class))->data();

    expect($data->properties)->toBe(['app_names' => ['Draft'], 'crm_name' => 'Lead', 'priority_name' => 'Low'])
        ->and($data->propertyTypes)->toBe([
            'app_names' => "('Draft' | 'Published')[]",
            'crm_name' => "'Lead' | 'Prospect' | 'Active' | 'Churned'",
            'priority_name' => 'PriorityKind',
        ])
        ->and($data->typeImports)->toBe(['../enums' => ['PriorityKind']]);
});

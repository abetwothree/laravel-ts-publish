<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\InertiaPageAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumWrapOrNameResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata\EnumNameMetadataProvider;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
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

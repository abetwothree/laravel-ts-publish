<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use Illuminate\Support\Str;

use function Orchestra\Testbench\workbench_path;

use Workbench\App\Events\PayloadDiffersEvent;
use Workbench\App\Http\Requests\StorePostRequest;
use Workbench\App\Http\Resources\CustomImportChannelResource;
use Workbench\App\Http\Resources\ImageDelegatedResource;
use Workbench\App\Http\Resources\TernaryResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\DiscriminatedPayload;
use Workbench\App\ValueObjects\EnumCasePayload;
use Workbench\App\ValueObjects\EnumValuesPayload;
use Workbench\App\ValueObjects\GuardedLinkPayload;
use Workbench\App\ValueObjects\PanelPayload;
use Workbench\App\ValueObjects\RedundantArmsPayload;
use Workbench\App\ValueObjects\ReshapedListsPayload;
use Workbench\App\ValueObjects\SameNamedKindsPayload;
use Workbench\App\ValueObjects\TraitPanelPayload;
use Workbench\App\ValueObjects\WidgetProps;

/**
 * Renders `AstEngine::analyze()`'s three fields into real `.ts` modules, mechanically — no
 * transformer, no Blade template, nothing that could repair a disagreement between them on the way
 * out. Whatever `analyze()` hands a consumer is what a consumer would have to write.
 *
 * The files land in the committed `testing` tree, so `unimportable-token-gate.sh` type-checks them
 * with `tsc` on every CI run: a token emitted without its import (TS2304), two imports colliding on
 * one local name (TS2300) and a dead import (TS6196) each fail that gate. It is the compilation
 * evidence; the assertions below restate the same three checks in a form that fails locally too.
 */
const ANALYSIS_PROBE_PATH = 'analysis-probe';

/**
 * The class/method pairs the probe renders, one module each.
 *
 * @return array<string, array{0: class-string, 1: string, 2: class-string|null}>
 */
function analysisProbeCases(): array
{
    return [
        // An EnumResource::make() prop: the README's own example shape.
        'UserResource' => [UserResource::class, 'toArray', null],
        // Two same-basename models from different namespaces, reached through model delegation.
        'ImageDelegatedResource' => [ImageDelegatedResource::class, 'toArray', null],
        // Same-basename enums AND models at once, several of them inside inline object types.
        'WarehouseResource' => [WarehouseResource::class, 'toArray', null],
        // A mixed ternary: one arm wraps the enum, the other reads it directly.
        'TernaryResource' => [TernaryResource::class, 'toArray', null],
        // #[TsType(import:)] tokens reaching three different result collectors.
        'CustomImportChannelResource' => [CustomImportChannelResource::class, 'toArray', null],
        // A broadcast event, the shape BroadcastEventTransformer runs on.
        'PayloadDiffersEvent' => [PayloadDiffersEvent::class, 'broadcastWith', null],
        // A model, which needs its own class as $modelClass to reach the delegated shape.
        'UserModel' => [User::class, 'toArray', User::class],
        // A form request, whose published interface is NOT an engine product — see the docs.
        'StorePostRequest' => [StorePostRequest::class, 'rules', null],
        // Enum `->value` and `->name` reads, each the published alias with its import or the literal union.
        'EnumValuesPayload' => [EnumValuesPayload::class, 'toArray', null],
        // An enum case, a local holding one and a ternary of cases read as receivers of their enum.
        'EnumCasePayload' => [EnumCasePayload::class, 'toArray', null],
        // Two same-named enums' `->value` and `->name` reads, each `…Kind` aliased with its `…Type`'s prefix.
        'SameNamedKindsPayload' => [SameNamedKindsPayload::class, 'toArray', null],
        // One enum's `…Type` token and its same-named sibling's `…Kind` token in one union.
        'SameNamedKindsUnions' => [SameNamedKindsPayload::class, 'unions', null],
        // Two same-named enums' `->name` reads inside one nested array, each `…Kind` aliased with its own prefix.
        'SameNamedKindsInner' => [SameNamedKindsPayload::class, 'inner', null],
        // array_values() and (object) and (array) casts of a list, a Record and an object literal, and stdClass values.
        'ReshapedListsPayload' => [ReshapedListsPayload::class, 'toArray', null],
        // A helper that returns the array it is passed, its writes joined onto the argument's shape.
        'GuardedLinkPayload' => [GuardedLinkPayload::class, 'toArray', null],
        // The same helper and its kin read as values: a nested argument, a +=, a declared shape and an unread one.
        'GuardedLinkPayloadValues' => [GuardedLinkPayload::class, 'values', null],
        // An inherited toArray() whose `$this`, `static`, `self` and `parent` each name another class at run time.
        'PanelPayload' => [PanelPayload::class, 'toArray', null],
        // A trait-supplied toArray() whose `self` and `parent` name the class that uses the trait and its parent.
        'TraitPanelPayload' => [TraitPanelPayload::class, 'toArray', null],
        // Case values beside the enum alias that admits them, and an empty list beside a list, at every join (GH-166).
        'RedundantArmsPayload' => [RedundantArmsPayload::class, 'toArray', null],
        // GH-166's own report: a branch rewrites an enum key with case values and empties a list property.
        'WidgetProps' => [WidgetProps::class, 'props', null],
        // Keys that follow a sibling key's value: a boolean at the top level, an enum match nested (GH-157).
        'DiscriminatedPayload' => [DiscriminatedPayload::class, 'toArray', null],
    ];
}

/**
 * Render one analysis as a standalone TypeScript module.
 */
function renderAnalysisProbe(string $name, AnalysisResult $result): string
{
    $members = [];

    foreach ($result->properties as $property) {
        // An index signature is already spelled as its own member; anything not a bare identifier
        // (a form request's dotted rule path, say) has to be quoted.
        if (str_starts_with($property['name'], '[')) {
            $members[] = '    '.$property['name'].': '.$property['type'].';';

            continue;
        }

        $key = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $property['name']) === 1
            ? $property['name']
            : "'".$property['name']."'";

        $members[] = '    '.$key.($property['optional'] ? '?' : '').': '.$property['type'].';';
    }

    $body = implode("\n", $members);
    $lines = [];

    if (str_contains($body, 'AsEnum<')) {
        $lines[] = "import { type AsEnum } from '@tolki/ts';";
    }

    foreach ($result->typeImports as $path => $names) {
        $lines[] = 'import type { '.implode(', ', $names)." } from '".$path."';";
    }

    // A value import binds a const the `AsEnum<typeof X>` wrapper reads, so it is never `import type`.
    foreach ($result->valueImports as $path => $names) {
        $lines[] = 'import { '.implode(', ', $names)." } from '".$path."';";
    }

    return "// Generated by tests/Feature/AnalyzeApiProbeTest.php from AstEngine::analyze(). Do not edit.\n\n"
        .($lines === [] ? '' : implode("\n", $lines)."\n\n")
        .'export interface '.$name."Probe\n{\n".$body."\n}\n";
}

/**
 * Every identifier in a rendered type that has to resolve to a declaration.
 *
 * @return list<string>
 */
function analysisProbeTokens(string $type): array
{
    $keywords = [
        'string', 'number', 'boolean', 'unknown', 'null', 'undefined', 'never', 'void', 'object',
        'bigint', 'symbol', 'true', 'false', 'any', 'keyof', 'typeof', 'readonly', 'infer', 'extends',
        'Record', 'Array', 'Partial', 'Pick', 'Omit', 'Readonly', 'Required', 'Exclude', 'Extract',
        'NonNullable', 'Date', 'AsEnum',
    ];

    // Drop string-literal types and every member NAME an inline object declares: neither resolves to
    // a declaration, and both are spelled exactly the way a type token is.
    $type = preg_replace('/\'[^\']*\'|"[^"]*"/', '', $type) ?? $type;
    $type = preg_replace('/[A-Za-z_$][A-Za-z0-9_$]*\s*\??\s*:/', ':', $type) ?? $type;

    preg_match_all('/[A-Za-z_$][A-Za-z0-9_$]*/', $type, $matches);

    return array_values(array_diff(array_unique($matches[0]), $keywords));
}

/**
 * The local name an import specifier binds — the alias when it carries `Original as Alias`.
 *
 * @return list<string>
 */
function analysisProbeBoundNames(AnalysisResult $result): array
{
    $bound = [];

    foreach ([$result->typeImports, $result->valueImports] as $map) {
        foreach ($map as $names) {
            foreach ($names as $specifier) {
                $position = strpos($specifier, ' as ');
                $bound[] = $position === false ? $specifier : substr($specifier, $position + 4);
            }
        }
    }

    return $bound;
}

it('renders every analyze() result as a TypeScript module the token gate compiles', function () {
    $engine = resolve(AstEngine::class);
    $directory = workbench_path('resources/js/types/data/testing/'.ANALYSIS_PROBE_PATH);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    foreach (analysisProbeCases() as $name => [$class, $method, $modelClass]) {
        file_put_contents(
            $directory.'/'.Str::kebab($name).'.ts',
            renderAnalysisProbe($name, $engine->analyze($class, $method, $modelClass, ANALYSIS_PROBE_PATH)),
        );
    }

    expect(glob($directory.'/*.ts'))->toHaveCount(count(analysisProbeCases()));
});

it('returns properties and imports that agree with each other', function (string $name) {
    // The one token the generated tree resolves from `declare global` instead of an import —
    // #[TsType] with no `import` key, which is the app's job to declare. Everything else must be
    // bound, and the token gate is what proves the bindings actually resolve.
    $globals = ['CustomObject'];

    [$class, $method, $modelClass] = analysisProbeCases()[$name];
    $result = resolve(AstEngine::class)->analyze($class, $method, $modelClass, ANALYSIS_PROBE_PATH);

    $bound = analysisProbeBoundNames($result);
    $spelled = analysisProbeTokens(implode("\n", array_column($result->properties, 'type')));
    $propertyNames = array_column($result->properties, 'name');

    expect($bound)->toBe(array_values(array_unique($bound)))
        ->and($propertyNames)->toBe(array_values(array_unique($propertyNames)));

    foreach ($bound as $localName) {
        expect($spelled)->toContain($localName);
    }

    foreach ($spelled as $token) {
        expect([...$bound, ...$globals])->toContain($token);
    }
})->with(array_keys(analysisProbeCases()));

// A case value an enum's alias beside it admits, and an empty list beside a list, go; each `*_kept` arm stays (GH-166).
it('publishes no arm another arm of its union admits', function (string $name, array $expected) {
    [$class, $method, $modelClass] = analysisProbeCases()[$name];
    $result = resolve(AstEngine::class)->analyze($class, $method, $modelClass, ANALYSIS_PROBE_PATH);

    expect(array_column($result->properties, 'type', 'name'))->toBe($expected);
})->with([
    'the matrix' => ['RedundantArmsPayload', [
        'case_beside_alias' => 'MediaTypeType',
        'case_beside_nullable_alias' => 'MediaTypeType | null',
        'coalesced_case' => 'MediaTypeType',
        'matched_cases' => 'MediaTypeType',
        'backed_name' => 'GradeKind',
        'pure_name' => 'VisibilityType',
        'int_case' => 'PriorityType',
        'nested' => '{ inner: MediaTypeType }',
        'listed' => 'MediaTypeType[]',
        'helper_case_beside_alias' => 'StatusType',
        'helper_case_value' => '0',
        'helper_case_name' => "'Draft'",
        'values_of_keyed' => 'string[]',
        'name_beside_value_alias_kept' => "'Image' | MediaTypeType",
        'other_enum_case_kept' => "'spring' | MediaTypeType",
        'cases_without_alias_kept' => "'image' | 'video'",
        'wrap_beside_case_kept' => "AsEnum<typeof MediaType> | 'image'",
        'wrap_and_read_kept' => "{ w: AsEnum<typeof MediaType>; v: 'image' | MediaTypeType }",
        'emptied_list' => 'PriorityType[]',
        'filled_list' => 'PriorityType[]',
        'nullable_list' => 'PriorityType[] | null',
        'record_beside_empty_kept' => 'never[] | Record<string, PriorityType>',
        'object_beside_empty_kept' => 'never[] | { a: number }',
        'folded_case' => 'MediaTypeType',
    ]],
    'GH-166\'s own report' => ['WidgetProps', [
        'kind' => 'MediaTypeType | null',
        'method' => 'string',
        'modes' => 'PriorityType[]',
    ]],
]);

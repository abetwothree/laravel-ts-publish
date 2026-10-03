@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@php
    // A DTO built without the combined lists or the shared keys renders as a model that shares no key with a relation.
    $columns = $data->combinedColumns ?? $data->columns;
    $mutators = $data->combinedMutators ?? $data->mutators;
    $appends = $data->combinedAppends ?? $data->appends;
    $enums = $data->combinedEnums ?? ($data->enumColumns + $data->enumMutators + $data->enumAppends);
    $typeImports = $data->combinedTypeImports ?? $data->typeImports;
    $valueImports = $data->combinedValueImports ?? $data->valueImports;
    $countKeys = $data->relationCountKeys ?? array_map(fn ($name) => $name . '_count', array_keys($data->relations));
    $existsKeys = $data->relationExistsKeys ?? array_map(fn ($name) => $name . '_exists', array_keys($data->relations));
@endphp
@if($usesTolkiPackage && count($valueImports) > 0)
import { type AsEnum } from '@tolki/ts';

@endif{{-- end tolki package --}}
@foreach ($valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@php
    $description = $data->description;

    if ($description) {
        $description .= "\n\n";
    }

    $description .= "@see {$data->fqcn}";
@endphp
{!! JsEmitter::formatJsDoc($description) !!}
export interface {{ $data->modelName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@if (count($columns) > 0)
    // Columns
@foreach ($columns as $name => $column)
@if($column['description'])
{!! JsEmitter::formatJsDoc($column['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name) !!}{{ $column['optional'] ? '?' : '' }}: {!!  $column['type'] !!};
@endforeach
@endif
@if (count($mutators) > 0 || count($appends) > 0)
    // Mutators
@foreach ($mutators as $name => $mutator)
@if($mutator['description'])
{!! JsEmitter::formatJsDoc($mutator['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name) !!}{{ $mutator['optional'] ? '?' : '' }}: {!!  $mutator['type'] !!};
@endforeach
@foreach ($appends as $name => $append)
@if($append['description'])
{!! JsEmitter::formatJsDoc($append['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name) !!}{{ $append['optional'] ? '?' : '' }}: {!!  $append['type'] !!};
@endforeach
@endif
@if (count($data->relations) > 0)
    // Relations
@foreach ($data->relations as $name => $relation)
@if($relation['description'])
{!! JsEmitter::formatJsDoc($relation['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name) !!}: {!!  $relation['type'] !!};
@endforeach
@if (count($countKeys) > 0)
    // Counts
@foreach ($countKeys as $key)
    {!! JsEmitter::validJsObjectKey($key) !!}: number;
@endforeach
@endif
@if (count($existsKeys) > 0)
    // Exists
@foreach ($existsKeys as $key)
    {!! JsEmitter::validJsObjectKey($key) !!}: boolean;
@endforeach
@endif
@endif
}
@if (count($enums) > 0)

@php
    $omitKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_keys($enums)));
@endphp
export interface {{ $data->modelName }}Resource extends Omit<{{ $data->modelName }}, {!! $omitKeys !!}>
{
@foreach ($enums as $name => $enum)
    {!! JsEmitter::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['isCollection'] ? '[]' : '' !!}{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif

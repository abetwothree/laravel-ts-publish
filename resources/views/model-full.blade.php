@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@if($usesTolkiPackage && count($data->combinedValueImports) > 0)
import { type AsEnum } from '@tolki/ts';

@endif{{-- end tolki package --}}
@foreach ($data->combinedValueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($data->combinedTypeImports as $path => $types)
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
@if (count($data->combinedColumns) > 0)
    // Columns
@foreach ($data->combinedColumns as $name => $column)
@if($column['description'])
{!! JsEmitter::formatJsDoc($column['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name) !!}{{ $column['optional'] ? '?' : '' }}: {!!  $column['type'] !!};
@endforeach
@endif
@if (count($data->combinedMutators) > 0 || count($data->combinedAppends) > 0)
    // Mutators
@foreach ($data->combinedMutators as $name => $mutator)
@if($mutator['description'])
{!! JsEmitter::formatJsDoc($mutator['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name) !!}{{ $mutator['optional'] ? '?' : '' }}: {!!  $mutator['type'] !!};
@endforeach
@foreach ($data->combinedAppends as $name => $append)
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
@if (count($data->relationCountKeys) > 0)
    // Counts
@foreach ($data->relationCountKeys as $key)
    {!! JsEmitter::validJsObjectKey($key) !!}: number;
@endforeach
@endif
@if (count($data->relationExistsKeys) > 0)
    // Exists
@foreach ($data->relationExistsKeys as $key)
    {!! JsEmitter::validJsObjectKey($key) !!}: boolean;
@endforeach
@endif
@endif
}
@if (count($data->combinedEnums) > 0)

@php
    $omitKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_keys($data->combinedEnums)));
@endphp
export interface {{ $data->modelName }}Resource extends Omit<{{ $data->modelName }}, {!! $omitKeys !!}>
{
@foreach ($data->combinedEnums as $name => $enum)
    {!! JsEmitter::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['isCollection'] ? '[]' : '' !!}{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif

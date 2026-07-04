@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($usesTolkiPackage && count($data->valueImports) > 0)
import { type AsEnum } from '@tolki/ts';

@endif{{-- end tolki package --}}
@foreach ($data->valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@php
    $description = $data->description;

    if ($description) {
        $description .= "\n\n";
    }

    $description .= "@see {$data->fqcn}";
@endphp
{!! LaravelTsPublish::formatJsDoc($description) !!}
export interface {{ $data->modelName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@if (count($data->columns) > 0)
    // Columns
@foreach ($data->columns as $name => $column)
@if($column['description'])
{!! LaravelTsPublish::formatJsDoc($column['description'], 4) !!}
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}{{ $column['optional'] ? '?' : '' }}: {!!  $column['type'] !!};
@endforeach
@endif
@if (count($data->mutators) > 0 || count($data->appends) > 0)
    // Mutators
@foreach ($data->mutators as $name => $mutator)
@if($mutator['description'])
{!! LaravelTsPublish::formatJsDoc($mutator['description'], 4) !!}
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}{{ $mutator['optional'] ? '?' : '' }}: {!!  $mutator['type'] !!};
@endforeach
@foreach ($data->appends as $name => $append)
@if($append['description'])
{!! LaravelTsPublish::formatJsDoc($append['description'], 4) !!}
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}{{ $append['optional'] ? '?' : '' }}: {!!  $append['type'] !!};
@endforeach
@endif
@if (count($data->relations) > 0)
    // Relations
@foreach ($data->relations as $name => $relation)
@if($relation['description'])
{!! LaravelTsPublish::formatJsDoc($relation['description'], 4) !!}
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $relation['type'] !!};
@endforeach
    // Counts
@foreach ($data->relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name . '_count') !!}: number;
@endforeach
    // Exists
@foreach ($data->relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name . '_exists') !!}: boolean;
@endforeach
@endif
}
@if (count($data->enumColumns) > 0 || count($data->enumMutators) > 0 || count($data->enumAppends) > 0)

@php
    $allEnumKeys = array_merge(array_keys($data->enumColumns), array_keys($data->enumMutators), array_keys($data->enumAppends));
    $omitKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", $allEnumKeys));
@endphp
export interface {{ $data->modelName }}Resource extends Omit<{{ $data->modelName }}, {!! $omitKeys !!}>
{
@foreach ($data->enumColumns as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
@foreach ($data->enumMutators as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
@foreach ($data->enumAppends as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif

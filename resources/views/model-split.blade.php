@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($usesTolkiPackage && (count($data->enumColumns) > 0 || count($data->enumMutators) > 0 || count($data->enumAppends) > 0))
import { type AsEnum } from '@tolki/ts';

@endif{{-- end tolki package --}}
@foreach ($data->valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

/**
@if($data->description)
 * {!! LaravelTsPublish::sanitizeJsDoc($data->description) !!}
 *
@endif
 * @see {{ $data->fqcn }}
 */
export interface {{ $data->modelName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@foreach ($data->columns as $name => $column)
@if($column['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($column['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $column['type'] !!};
@endforeach
@foreach ($data->appends as $name => $append)
@if($append['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($append['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $append['type'] !!};
@endforeach
}
@if($usesTolkiPackage && (count($data->enumColumns) > 0 || count($data->enumAppends) > 0))

@php
$colKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_merge(array_keys($data->enumColumns), array_keys($data->enumAppends))));
$hasEnumsExtends = 'Omit<' . $data->modelName . ', ' . $colKeys . '>';
@endphp
export interface {{ $data->modelName }}Resource extends {!! $hasEnumsExtends !!}
{
@foreach ($data->enumColumns as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
@foreach ($data->enumAppends as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif{{-- end $data->enumColumns --}}
@if (count($data->mutators) > 0)

export interface {{ $data->modelName }}Mutators
{
@foreach ($data->mutators as $name => $mutator)
@if($mutator['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($mutator['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $mutator['type'] !!};
@endforeach
}
@if($usesTolkiPackage && count($data->enumMutators) > 0)

@php
$colKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_keys($data->enumMutators)));
$hasEnumsExtends = 'Omit<' . $data->modelName . 'Mutators, ' . $colKeys . '>';
@endphp
export interface {{ $data->modelName }}MutatorsResource extends {!! $hasEnumsExtends !!}
{
@foreach ($data->enumMutators as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif{{-- end $data->enumMutators --}}
@endif{{-- end $data->mutators --}}
@if (count($data->relations) > 0)

export interface {{ $data->modelName }}Relations
{
    // Relations
@foreach ($data->relations as $name => $relation)
@if($relation['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($relation['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $relation['type'] !!};
@endforeach
    // Counts
@foreach ($data->relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name.'_count') !!}: number;
@endforeach
    // Exists
@foreach ($data->relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name.'_exists') !!}: boolean;
@endforeach
}
@endif{{-- end $data->relations --}}
@if(count($data->mutators) > 0 || count($data->relations) > 0 || count($data->appends) > 0)

@php
$extends = [];

if(count($data->columns) > 0 || count($data->appends) > 0) {
    $extends[] = $data->modelName;
}

if(count($data->mutators) > 0) {
    $extends[] = $data->modelName.'Mutators';
}

if(count($data->relations) > 0) {
    $extends[] = $data->modelName.'Relations';
}
@endphp
export interface {{ $data->modelName }}All extends {{ implode(', ', $extends) }} {}
@endif{{-- end all extends --}}
@if($usesTolkiPackage && (count($data->enumColumns) > 0 || count($data->enumMutators) > 0 || count($data->enumAppends) > 0))

@php
$extends = (count($data->enumColumns) > 0 || count($data->enumAppends) > 0) ? [$data->modelName.'Resource'] : [$data->modelName];

if(count($data->mutators) > 0) {
    $mutators =  $data->modelName.'Mutators';
    $extends[] = count($data->enumMutators) > 0 ? $mutators.'Resource' : $mutators;
}

if(count($data->relations) > 0) {
    $extends[] = $data->modelName.'Relations';
}
@endphp
export interface {{ $data->modelName }}AllResource extends {{ implode(', ', $extends) }} {}
@endif{{-- end all has enums extends --}}

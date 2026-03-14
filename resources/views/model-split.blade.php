@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($usesTolkiPackage && (count($data->enumColumns) > 0 || count($data->enumMutators) > 0))
import { type AsEnum } from '@tolki/enum';

@endif{{-- end tolki package --}}
@foreach ($data->resolvedImports as $path => $types)
import { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@if (count($data->columns) > 0)
@if($data->description)
/** {!! LaravelTsPublish::sanitizeJsDoc($data->description) !!} */
@endif
export interface {{ $data->modelName }}
{
@foreach ($data->columns as $name => $column)
@if($column['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($column['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $column['type'] !!};
@endforeach
}
@if($usesTolkiPackage && count($data->enumColumns) > 0)

@php
$colKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_keys($data->enumColumns)));
$hasEnumsExtends = 'Omit<' . $data->modelName . ', ' . $colKeys . '>';
@endphp
export interface {{ $data->modelName }}HasEnum extends {!! $hasEnumsExtends !!}
{
@foreach ($data->enumColumns as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif{{-- end $data->enumColumns --}}
@endif{{-- end $data->columns --}}
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
export interface {{ $data->modelName }}MutatorsHasEnum extends {!! $hasEnumsExtends !!}
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
@if(count($data->mutators) > 0 || count($data->relations) > 0)

@php
$extends = [$data->modelName];
if(count($data->mutators) > 0) {
    $extends[] = $data->modelName.'Mutators';
}
if(count($data->relations) > 0) {
    $extends[] = $data->modelName.'Relations';
}
@endphp
export interface {{ $data->modelName }}All extends {{ implode(', ', $extends) }} {}
@endif{{-- end all extends --}}
@if($usesTolkiPackage && (count($data->enumColumns) > 0 || count($data->enumMutators) > 0))

@php
$extends = count($data->enumColumns) > 0 ? [$data->modelName.'HasEnum'] : [$data->modelName];

if(count($data->mutators) > 0) {
    $mutators =  $data->modelName.'Mutators';
    $extends[] = count($data->enumMutators) > 0 ? $mutators.'HasEnum' : $mutators;
}

if(count($data->relations) > 0) {
    $extends[] = $data->modelName.'Relations';
}
@endphp
export interface {{ $data->modelName }}AllHasEnum extends {{ implode(', ', $extends) }} {}
@endif{{-- end all has enums extends --}}


{{-- @if (count($data->enumColumns) > 0 || count($data->enumMutators) > 0)

@php
$hasEnumsExtends = [];
if (count($data->columns) > 0) {
    if (count($data->enumColumns) > 0) {
        $colKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_keys($data->enumColumns)));
        $hasEnumsExtends[] = 'Omit<' . $data->modelName . ', ' . $colKeys . '>';
    } else {
        $hasEnumsExtends[] = $data->modelName;
    }
}

if (count($data->mutators) > 0) {
    if (count($data->enumMutators) > 0) {
        $mutKeys = implode(' | ', array_map(fn($k) => "'" . $k . "'", array_keys($data->enumMutators)));
        $hasEnumsExtends[] = 'Omit<' . $data->modelName . 'Mutators, ' . $mutKeys . '>';
    } else {
        $hasEnumsExtends[] = $data->modelName . 'Mutators';
    }
}
@endphp
export interface {{ $data->modelName }}HasEnumsOrig extends {!! implode(', ', $hasEnumsExtends) !!}
{
@foreach ($data->enumColumns as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
@foreach ($data->enumMutators as $name => $enum)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: AsEnum<typeof {!! $enum['constName'] !!}>{!! $enum['nullable'] ? ' | null' : '' !!};
@endforeach
}
@endif --}}

@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach ($data->resolvedImports as $path => $types)
{{ $useTypeImports ? 'import type' : 'import' }} { {{ implode(', ', $types) }} } from '{{ $path }}';
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
@endif
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
@endif
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
@endif
@if (count($data->relations) > 0)
@endif
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
@endif

@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach ($resolvedImports as $path => $types)
import { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@if (count($columns) > 0)
export interface {{ $modelName }}
{
@foreach ($columns as $name => $column)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $column !!};
@endforeach
}
@endif
@if (count($mutators) > 0)

export interface {{ $modelName }}Mutators
{
@foreach ($mutators as $name => $mutator)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $mutator !!};
@endforeach
}
@endif
@if (count($relations) > 0)

export interface {{ $modelName }}Relations
{
    // Relations
@foreach ($relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $relation !!};
@endforeach
    // Counts
@foreach ($relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name.'_count') !!}: number;
@endforeach
    // Exists
@foreach ($relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name.'_exists') !!}: boolean;
@endforeach
}
@endif
@if (count($relations) > 0)
@endif
@if(count($mutators) > 0 || count($relations) > 0)

@php
$extends = [$modelName];
if(count($mutators) > 0) {
    $extends[] = $modelName.'Mutators';
}
if(count($relations) > 0) {
    $extends[] = $modelName.'Relations';
}
@endphp
export interface {{ $modelName }}All extends {{ implode(', ', $extends) }} {}
@endif

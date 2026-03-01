@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if(count($enumImports) > 0)
import { {{ implode(', ', $enumImports) }} } from '../enums';
@endif
@if(count($modelImports) > 0)
import { {{ implode(', ', $modelImports) }} } from './';
@endif

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
@foreach ($relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $relation !!};
@endforeach
}
@endif
@if (count($relations) > 0)

export interface {{ $modelName }}RelationCounts
{
@foreach ($relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name.'_count') !!}: number;
@endforeach
}
@endif
@if (count($relations) > 0)

export interface {{ $modelName }}RelationExists
{
@foreach ($relations as $name => $relation)
    {!! LaravelTsPublish::validJsObjectKey($name.'_exists') !!}: boolean;
@endforeach
}
@endif

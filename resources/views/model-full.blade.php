@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if(count($enumImports) > 0)
import { {{ implode(', ', $enumImports) }} } from '../enums';
@endif
@if(count($modelImports) > 0)
import { {{ implode(', ', $modelImports) }} } from './';
@endif
@foreach ($customImports as $path => $types)
import { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@if (count($columns) > 0 || count($mutators) > 0 || count($relations) > 0)
export interface {{ $modelName }}
{
@if (count($columns) > 0)
    // Columns
@foreach ($columns as $name => $column)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $column !!};
@endforeach
@endif
@if (count($mutators) > 0)
    // Mutators
@foreach ($mutators as $name => $mutator)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $mutator !!};
@endforeach
@endif
@if (count($relations) > 0)
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
@endif
}
@endif

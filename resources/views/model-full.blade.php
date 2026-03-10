@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach ($resolvedImports as $path => $types)
{{ $useTypeImports ? 'import type' : 'import' }} { {{ implode(', ', $types) }} } from '{{ $path }}';
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

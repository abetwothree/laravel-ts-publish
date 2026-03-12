@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach ($resolvedImports as $path => $types)
{{ $useTypeImports ? 'import type' : 'import' }} { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@if (count($columns) > 0 || count($mutators) > 0 || count($relations) > 0)
@if($description)
/** {{ LaravelTsPublish::sanitizeJsDoc($description) }} */
@endif
export interface {{ $modelName }}
{
@if (count($columns) > 0)
    // Columns
@foreach ($columns as $name => $column)
@if($column['description'])
    /** {{ LaravelTsPublish::sanitizeJsDoc($column['description']) }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $column['type'] !!};
@endforeach
@endif
@if (count($mutators) > 0)
    // Mutators
@foreach ($mutators as $name => $mutator)
@if($mutator['description'])
    /** {{ LaravelTsPublish::sanitizeJsDoc($mutator['description']) }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $mutator['type'] !!};
@endforeach
@endif
@if (count($relations) > 0)
    // Relations
@foreach ($relations as $name => $relation)
@if($relation['description'])
    /** {{ LaravelTsPublish::sanitizeJsDoc($relation['description']) }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!!  $relation['type'] !!};
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

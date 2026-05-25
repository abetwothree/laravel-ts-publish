@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($usesTolkiPackage && count($data->valueImports) > 0)
import { type AsEnum } from '@tolki/ts';

@endif
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
@if($data->typeAlias !== null)
export type {{ $data->resourceName }} = {!! $data->typeAlias !!};
@else
export interface {{ $data->resourceName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@foreach ($data->properties as $name => $property)
@if($property['description'])
{!! LaravelTsPublish::formatJsDoc($property['description'], 4) !!}
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}{!! $property['optional'] ? '?' : '' !!}: {!! $property['type'] !!};
@endforeach
}
@endif

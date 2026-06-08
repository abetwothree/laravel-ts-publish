@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach
@if(count($data->typeImports) > 0)

@endif
{!! LaravelTsPublish::formatJsDoc($data->description) !!}
export interface {{ $data->eventName }} {
@foreach ($data->properties as $name => $prop)
@php
    $tsType = $prop['type'];
    $optional = $prop['optional'] ? '?' : '';
@endphp
    {{ LaravelTsPublish::validJsObjectKey($name) }}{{ $optional }}: {!! $tsType !!};
@endforeach
}

@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach
@if(count($data->typeImports) > 0)

@endif
{!! JsEmitter::formatJsDoc($data->description) !!}
export interface {{ $data->eventName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!} {
@foreach ($data->properties as $name => $prop)
@php
    $tsType = $prop['type'];
    $optional = $prop['optional'] ? '?' : '';
@endphp
    {!! JsEmitter::validJsObjectKey($name) !!}{{ $optional }}: {!! $tsType !!};
@endforeach
}

@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach
@foreach ($data->valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@if(count($data->typeImports) > 0 || count($data->valueImports) > 0)

@endif
{!! JsEmitter::formatJsDoc($data->description) !!}
export interface {{ $data->eventName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!} {
@foreach ($data->properties as $name => $prop)
@php
    $tsType = $prop['type'];
    $optional = $prop['optional'] && ! JsEmitter::isIndexSignatureKey($name) ? '?' : '';
@endphp
    {!! JsEmitter::validJsObjectKey($name, allowIndexSignature: true) !!}{{ $optional }}: {!! $tsType !!};
@endforeach
}

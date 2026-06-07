@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
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

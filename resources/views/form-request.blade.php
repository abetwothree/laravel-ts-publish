@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($data->isDynamic)
/**
 * @see {{ $data->fqcn }}
 * @dynamic Rules could not be resolved statically.
 */
export type {{ $data->typeName }} = Record<string, unknown>;
@else
/**
 * @see {{ $data->fqcn }}
 */
export interface {{ $data->typeName }} {
@foreach ($data->fields as $field)
@if($field['isProhibited'])
@else
@php
$fieldType = $field['tsType'];
if ($field['isNullable']) {
    $fieldType .= ' | null';
}
$optional = ! $field['isRequired'] ? '?' : '';
@endphp
@if(count($field['jsDocMetadata']) > 0)
{!! LaravelTsPublish::formatJsDoc(implode("\n", $field['jsDocMetadata']), 4) !!}
@endif
    {!! LaravelTsPublish::validJsObjectKey($field['fieldPath']) !!}{{ $optional }}: {!! $fieldType !!};
@endif
@endforeach
}
@endif

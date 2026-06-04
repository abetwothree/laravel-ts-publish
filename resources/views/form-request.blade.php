@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@php
$formRequestDoc = $data->description !== '' ? $data->description . "\n\n" : '';
$formRequestDoc .= "@see {$data->fqcn}";

if ($data->isDynamic) {
    $formRequestDoc .= "\n@dynamic Rules could not be resolved statically.";
}
@endphp
@if($data->isDynamic)
{!! LaravelTsPublish::formatJsDoc($formRequestDoc) !!}
export type {{ $data->typeName }} = Record<string, unknown>;
@else
{!! LaravelTsPublish::formatJsDoc($formRequestDoc) !!}
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

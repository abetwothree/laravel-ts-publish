@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@if($metadataEnabled && $usesTolkiPackage)
import { defineEnum } from '@tolki/ts';

@endif
@php
    $description = $data->description;

    if($description){
        $description .= "\n\n";
    }

    $description .= "@see {$data->fqcn}";
@endphp
{!! JsEmitter::formatJsDoc($description) !!}
@if($metadataEnabled && $usesTolkiPackage)
export const {{ $data->enumName }} = defineEnum({
@else
export const {{ $data->enumName }} = {
@endif
@foreach ($data->cases as $case)
@if($case['description'])
{!! JsEmitter::formatJsDoc($case['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($case['name']) !!}: {!! JsEmitter::toJsLiteral($case['value']) !!},
@endforeach
@if($metadataEnabled)
    backed: {{ $data->backed ? 'true' : 'false' }},
@endif
@foreach ($data->methods as $methodName => $method)
@if($method['description'])
{!! JsEmitter::formatJsDoc($method['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($method['name']) !!}: {
@foreach ($method['returns'] as $caseName => $returnValue)
        {!! JsEmitter::validJsObjectKey($caseName) !!}: {!! JsEmitter::toJsLiteral($returnValue) !!},
@endforeach
    },
@endforeach
@foreach ($data->staticMethods as $methodName => $method)
@if($method['description'])
{!! JsEmitter::formatJsDoc($method['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($method['name']) !!}: {!! JsEmitter::toJsLiteral($method['return']) !!},
@endforeach
@if($metadataEnabled && count($data->cases) > 0)
    _cases: [{!! implode(', ', $data->backed ? $data->caseKinds : $data->caseTypes) !!}],
@endif
@if($metadataEnabled && count($data->methods) > 0)
    _methods: [{!! implode(', ', array_map(fn($method) => JsEmitter::toJsLiteral($method['name']), $data->methods)) !!}],
@endif
@if($metadataEnabled && count($data->staticMethods) > 0)
    _static: [{!! implode(', ', array_map(fn($method) => JsEmitter::toJsLiteral($method['name']), $data->staticMethods)) !!}],
@endif
@if($metadataEnabled && $usesTolkiPackage)
} as const);
@else
} as const;
@endif

export type {{ $data->enumName }}Type = {!! implode(' | ', $data->caseTypes) !!};
@if($data->backed)

export type {{ $data->enumName }}Kind = {!! implode(' | ', $data->caseKinds) !!};
@endif

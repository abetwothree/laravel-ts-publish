@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($metadataEnabled && $usesTolkiPackage)
import { defineEnum } from '@tolki/enum';

@endif
@if($data->description)
/** {!! LaravelTsPublish::sanitizeJsDoc($data->description) !!} */
@endif
@if($metadataEnabled && $usesTolkiPackage)
export const {{ $data->enumName }} = defineEnum({
@else
export const {{ $data->enumName }} = {
@endif
@foreach ($data->cases as $case)
@if($case['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($case['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($case['name']) !!}: {!! LaravelTsPublish::toJsLiteral($case['value']) !!},
@endforeach
@if($metadataEnabled)
    backed: {{ $data->backed ? 'true' : 'false' }},
@endif
@foreach ($data->methods as $methodName => $method)
@if($method['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($method['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {
@foreach ($method['returns'] as $caseName => $returnValue)
        {!! LaravelTsPublish::validJsObjectKey($caseName) !!}: {!! LaravelTsPublish::toJsLiteral($returnValue) !!},
@endforeach
    },
@endforeach
@foreach ($data->staticMethods as $methodName => $method)
@if($method['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($method['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {!! LaravelTsPublish::toJsLiteral($method['return']) !!},
@endforeach
@if($metadataEnabled && count($data->cases) > 0)
    _cases: [{!! implode(', ', $data->backed ? $data->caseKinds : $data->caseTypes) !!}],
@endif
@if($metadataEnabled && count($data->methods) > 0)
    _methods: [{!! implode(', ', array_map(fn($method) => LaravelTsPublish::toJsLiteral($method['name']), $data->methods)) !!}],
@endif
@if($metadataEnabled && count($data->staticMethods) > 0)
    _static: [{!! implode(', ', array_map(fn($method) => LaravelTsPublish::toJsLiteral($method['name']), $data->staticMethods)) !!}],
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

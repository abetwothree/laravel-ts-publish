@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($metadataEnabled && $usesTolkiPackage)
import { defineEnum } from '@tolki/enum';

@endif
@if($metadataEnabled && $usesTolkiPackage)
export const {{ $enumName }} = defineEnum({
@else
export const {{ $enumName }} = {
@endif
@foreach ($cases as $case)
@if($case['description'])
    /** {{ LaravelTsPublish::sanitizeJsDoc($case['description']) }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($case['name']) !!}: {!! LaravelTsPublish::toJsLiteral($case['value']) !!},
@endforeach
@foreach ($methods as $methodName => $method)
@if($method['description'])
    /** {{ LaravelTsPublish::sanitizeJsDoc($method['description']) }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {
@foreach ($method['returns'] as $caseName => $returnValue)
        {!! LaravelTsPublish::validJsObjectKey($caseName) !!}: {!! LaravelTsPublish::toJsLiteral($returnValue) !!},
@endforeach
    },
@endforeach
@foreach ($staticMethods as $methodName => $method)
@if($method['description'])
    /** {{ LaravelTsPublish::sanitizeJsDoc($method['description']) }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {!! LaravelTsPublish::toJsLiteral($method['return']) !!},
@endforeach
@if($metadataEnabled)
    _cases: [{!! implode(', ', $backed ? $caseKinds : $caseTypes) !!}],
    _methods: [{!! implode(', ', array_map(fn($method) => LaravelTsPublish::toJsLiteral($method['name']), $methods)) !!}],
    _static: [{!! implode(', ', array_map(fn($method) => LaravelTsPublish::toJsLiteral($method['name']), $staticMethods)) !!}],
@endif
@if($metadataEnabled && $usesTolkiPackage)
} as const);
@else
} as const;
@endif

export type {{ $enumName }}Type = {!! implode(' | ', $caseTypes) !!};
@if($backed)

export type {{ $enumName }}Kind = {!! implode(' | ', $caseKinds) !!};
@endif

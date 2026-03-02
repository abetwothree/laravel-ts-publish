@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
export const {{ $enumName }} = {
@foreach ($cases as $case)
@if($case['description'])
    /** {{ $case['description'] }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($case['name']) !!}: {!! LaravelTsPublish::toJsLiteral($case['value']) !!},
@endforeach
@foreach ($methods as $methodName => $method)
@if($method['description'])
    /** {{ $method['description'] }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {
@foreach ($method['returns'] as $caseName => $returnValue)
        {!! LaravelTsPublish::validJsObjectKey($caseName) !!}: {!! LaravelTsPublish::toJsLiteral($returnValue) !!},
@endforeach
    },
@endforeach
@foreach ($staticMethods as $methodName => $method)
@if($method['description'])
    /** {{ $method['description'] }} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {!! LaravelTsPublish::toJsLiteral($method['return']) !!},
@endforeach
} as const;

export type {{ $enumName }}Type = {!! implode(' | ', $caseTypes) !!};
@if($backed)

export type {{ $enumName }}Kind = {!! implode(' | ', $caseKinds) !!};
@endif

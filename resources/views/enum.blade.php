@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
export const {{ $enumName }} = {
@foreach ($cases as $case)
@if($case['description'])
    /**
     * {{ $case['description'] }}
     */
@endif
    {!! LaravelTsPublish::validJsObjectKey($case['name']) !!}: @js($case['value']),
@endforeach
@foreach ($staticMethods as $methodName => $method)
@if($method['description'])
    /**
     * {{ $method['description'] }}
     */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: @js($method['return']),
@endforeach
@foreach ($methods as $methodName => $method)
@if($method['description'])
    /**
     * {{ $method['description'] }}
     */
@endif
    {!! LaravelTsPublish::validJsObjectKey($method['name']) !!}: {
@foreach ($method['returns'] as $caseName => $returnValue)
        {!! LaravelTsPublish::validJsObjectKey($caseName) !!}: @js($returnValue),
@endforeach
    },
@endforeach
} as const;

export type {{ $enumName }}Type = {!! implode(' | ', $caseTypes) !!};
@if($backed)

export type {{ $enumName }}Kind = {!! implode(' | ', $caseKinds) !!};
@endif

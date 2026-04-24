@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@use('Illuminate\Support\Str')
@if(count($data->actions) === 0)
export {}
@else
import { defineRoute } from '@tolki/ts';
@foreach ($data->actions as $action)

@if(isset($action['pageType']))
@if(is_array($action['pageType']))
@foreach($action['pageType'] as $pageTypeKey => $pageTypeValue)
export type {!! Str::studly($action['methodName']) . Str::studly($pageTypeKey) !!}PageProps = {!! $pageTypeValue !!};
@endforeach
@else
export type {!! Str::studly($action['methodName']) !!}PageProps = {!! $action['pageType'] !!};
@endif

@endif
@if($action['description'])
/** {!! LaravelTsPublish::sanitizeJsDoc($action['description']) !!} */
@endif
export const {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!} = defineRoute({
@if($action['name'] !== null)
    name: {!! LaravelTsPublish::toJsLiteral($action['name']) !!},
@endif
@if($action['url'] !== null)
    url: {!! LaravelTsPublish::toJsLiteral($action['url']) !!},
@else
    url: {!! LaravelTsPublish::toJsLiteral($action['uri']) !!},
@endif
@if($action['domain'] !== null)
    domain: {!! LaravelTsPublish::toJsLiteral($action['domain']) !!},
@endif
    methods: [{!! implode(', ', array_map(fn($m) => "'$m'", $action['methods'])) !!}] as const,
@if(!empty($action['args']))
    args: {!! LaravelTsPublish::routeArgsToJs($action['args']) !!} as const,
@endif
@if(isset($action['component']))
@if(is_array($action['component']))
    component: {!! LaravelTsPublish::toJsLiteral($action['component']) !!} as const,
@else
    component: {!! LaravelTsPublish::toJsLiteral($action['component']) !!},
@endif
@endif
});
@endforeach

@php
$controllerName = LaravelTsPublish::safeJsIdentifier($data->controllerName, 'Controller');
@endphp
/**
@if($data->description)
 * {!! LaravelTsPublish::sanitizeJsDoc($data->description) !!}
 *
@endif
 * @see {{ $data->fqcn }}
 */
const {!! $controllerName !!} = {
@foreach ($data->actions as $action)
@if($action['originalMethodName'] === $action['methodName'])
    {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!},
@else
    {!! LaravelTsPublish::toJsLiteral($action['originalMethodName']) !!}: {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!},
@endif
@endforeach
};

export default {!! $controllerName !!};
@endif

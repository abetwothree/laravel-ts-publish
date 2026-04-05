@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if(count($data->actions) === 0)
export {}
@else
import { defineRoute } from '@tolki/ts/routes';
@foreach ($data->actions as $action)

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
    '{!! $action['originalMethodName'] !!}': {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!},
@endif
@endforeach
};
@endif

export default {!! $controllerName !!};

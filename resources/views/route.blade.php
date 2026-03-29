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
    url: {!! LaravelTsPublish::toJsLiteral('/'.ltrim($action['url'], '/')) !!},
    methods: [{!! implode(', ', array_map(fn($m) => "'$m'", $action['methods'])) !!}] as const,
@if(!empty($action['args']))
    args: {!! LaravelTsPublish::routeArgsToJs($action['args']) !!} as const,
@endif
});
@endforeach

/**
@if($data->description)
 * {!! LaravelTsPublish::sanitizeJsDoc($data->description) !!}
 *
@endif
 * @see {{ $data->fqcn }}
 */
const {{ $data->controllerName }} = {
@foreach ($data->actions as $action)
    {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!},
@endforeach
};
@endif

export default {{ $data->controllerName }};

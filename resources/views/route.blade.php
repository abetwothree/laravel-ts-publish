@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@use('Illuminate\Support\Str')
@if(count($data->actions) === 0)
export {}
@else
@php
$importParts = ['defineRoute'];

if ($data->hasPageTypes) {
    $importParts[] = 'annotatePageProps';
}

if ($data->hasRequestTypes) {
    $importParts[] = 'annotateRequestPayload';
}

$imports = implode(', ', $importParts);
@endphp
import { {!! $imports !!} } from '@tolki/ts';
@if(count($data->typeImports) > 0)

@foreach($data->typeImports as $importPath => $typeNames)
import type { {!! implode(', ', $typeNames) !!} } from '{!! $importPath !!}';
@endforeach
@endif
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
@php
$hasRequest = isset($action['requestFqcn']);
$needExtraSpacing = $action['shouldAnnotate'] || $action['hasFormRequest'] || $hasRequest;
@endphp
@if(!isset($action['pageType']))

@endif{{-- blank line between each export const; pageType block already has a trailing blank --}}
@if($action['description'])
/**
  * {!! LaravelTsPublish::sanitizeJsDoc($action['description']) !!}
  */
@endif
@if($action['shouldAnnotate'] && $hasRequest)
export const {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!} = annotateRequestPayload<{!! $action['requestTypeAlias'] !!}>()(annotatePageProps<{!! $action['pageTypeAnnotation'] !!}>()(defineRoute({
@elseif($action['shouldAnnotate'])
export const {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!} = annotatePageProps<{!! $action['pageTypeAnnotation'] !!}>()(defineRoute({
@elseif($hasRequest)
export const {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!} = annotateRequestPayload<{!! $action['requestTypeAlias'] !!}>()(defineRoute({
@else
export const {!! LaravelTsPublish::validJsObjectKey($action['methodName']) !!} = defineRoute({
@endif
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
@if($action['shouldAnnotate'] && $hasRequest)
})));
@elseif($needExtraSpacing)
}));
@else
});
@endif
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

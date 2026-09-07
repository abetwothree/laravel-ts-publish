@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@if ($loop->last)

@endif
@endforeach
export const {{ $data->modelName }}ModelMetadata = {
@foreach ($data->properties as $name => $value)
    {!! JsEmitter::validJsObjectKey($name) !!}: {!! JsEmitter::toJsLiteral($value) !!},
@endforeach
} as const satisfies {
@foreach ($data->propertyTypes as $name => $type)
    {!! JsEmitter::validJsObjectKey($name) !!}: {!! $type !!};
@endforeach
};

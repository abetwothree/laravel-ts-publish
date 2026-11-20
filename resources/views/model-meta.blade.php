@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach
@foreach ($data->valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@if (count($data->typeImports) > 0 || count($data->valueImports) > 0)

@endif
export const {{ $data->modelName }}ModelMetadata = {
@foreach ($data->properties as $name => $value)
    {!! JsEmitter::validJsObjectKey($name) !!}: {!! JsEmitter::toJsLiteral($value) !!},
@endforeach
} as const satisfies {
@foreach ($data->propertyTypes as $name => $type)
    {!! JsEmitter::validJsObjectKey($name) !!}: {!! $type !!};
@endforeach
};

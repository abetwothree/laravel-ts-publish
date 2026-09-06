@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@if ($loop->last)

@endif
@endforeach
export const {{ $data->modelName }}ModelMetadata = {
@foreach ($data->properties as $name => $value)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!! LaravelTsPublish::toJsLiteral($value) !!},
@endforeach
} as const satisfies {
@foreach ($data->propertyTypes as $name => $type)
    {!! LaravelTsPublish::validJsObjectKey($name) !!}: {!! $type !!};
@endforeach
};

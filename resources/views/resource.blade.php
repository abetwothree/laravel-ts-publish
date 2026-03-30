@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($usesTolkiPackage && count($data->valueImports) > 0)
import { type AsEnum } from '@tolki/enum';

@endif
@foreach ($data->valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@if($data->description)
/** {!! LaravelTsPublish::sanitizeJsDoc($data->description) !!} */
@endif
export interface {{ $data->resourceName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@foreach ($data->properties as $name => $property)
@if($property['description'])
    /** {!! LaravelTsPublish::sanitizeJsDoc($property['description']) !!} */
@endif
    {!! LaravelTsPublish::validJsObjectKey($name) !!}{!! $property['optional'] ? '?' : '' !!}: {!! $property['type'] !!};
@endforeach
}

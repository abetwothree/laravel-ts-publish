@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@use('AbeTwoThree\LaravelTsPublish\Support\VariantRows')
@if($usesTolkiPackage && count($data->valueImports) > 0)
import { type AsEnum } from '@tolki/ts';

@endif
@foreach ($data->valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach

@php
$description = $data->description;

if ($description) {
    $description .= "\n\n";
}

$description .= "@see {$data->fqcn}";
@endphp
{!! JsEmitter::formatJsDoc($description) !!}
@if($data->typeAlias !== null)
export type {{ $data->resourceName }} = {!! $data->typeAlias !!};
@elseif(count($data->variants) === 1 && count($data->properties) === 0 && count($data->tsExtends) === 0)
export type {{ $data->resourceName }} =
@foreach (VariantRows::arms($data->variants[0]) as $arm)
    | {!! $arm !!}{!! $loop->last ? ';' : '' !!}
@endforeach
@else
@if(count($data->variants) === 0)
export interface {{ $data->resourceName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@else
export type {{ $data->resourceName }} = {!! implode('', array_map(fn (string $extends): string => $extends.' & ', $data->tsExtends)) !!}{!! count($data->properties) > 0 ? '{' : '(' !!}
@endif
@foreach ($data->properties as $name => $property)
@if($property['description'])
{!! JsEmitter::formatJsDoc($property['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name, allowIndexSignature: true) !!}{!! $property['optional'] && ! JsEmitter::isIndexSignatureKey($name) ? '?' : '' !!}: {!! $property['type'] !!};
@endforeach
@if(count($data->variants) === 0)
}
@elseif(count($data->properties) > 0)
} & (
@endif
@foreach ($data->variants as $variant)
@foreach (VariantRows::arms($variant) as $arm)
    | {!! $arm !!}
@endforeach
){!! $loop->last ? ';' : ' & (' !!}
@endforeach
@endif

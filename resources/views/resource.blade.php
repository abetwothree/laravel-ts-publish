@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@use('AbeTwoThree\LaravelTsPublish\Facades\TsTypeString')
@use('AbeTwoThree\LaravelTsPublish\Support\ResourceDeclaration')
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
$declaration = ResourceDeclaration::of($data->tsExtends, $data->properties, $data->variants, $data->intersections);
@endphp
{!! JsEmitter::formatJsDoc($description) !!}
@if($data->typeAlias !== null)
export type {{ $data->resourceName }} = {!! $data->typeAlias !!};
@elseif($declaration->armsAlone)
export type {{ $data->resourceName }} =
@foreach (TsTypeString::splitTopLevelUnion($data->variants[0]) as $arm)
    | {!! $arm !!}{!! $loop->last ? ';' : '' !!}
@endforeach
@else
@if($declaration->interface)
export interface {{ $data->resourceName }}{!! count($data->tsExtends) > 0 ? ' extends ' . implode(', ', $data->tsExtends) : '' !!}
{
@else
export type {{ $data->resourceName }} = {!! $declaration->open !!}
@endif
@foreach ($data->properties as $name => $property)
@if($property['description'])
{!! JsEmitter::formatJsDoc($property['description'], 4) !!}
@endif
    {!! JsEmitter::validJsObjectKey($name, allowIndexSignature: true) !!}{!! $property['optional'] && ! JsEmitter::isIndexSignatureKey($name) ? '?' : '' !!}: {!! $property['type'] !!};
@endforeach
@if($declaration->close !== null)
{!! $declaration->close !!}
@endif
@foreach ($data->variants as $variant)
@foreach (TsTypeString::splitTopLevelUnion($variant) as $arm)
    | {!! $arm !!}
@endforeach
{!! $declaration->closeArms($loop->last) !!}
@endforeach
@endif

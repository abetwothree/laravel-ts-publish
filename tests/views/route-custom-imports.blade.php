{{-- A customised route template that keeps the type imports loop and renders no `usesTolkiPackage` flag. --}}
import { defineRoute } from '@tolki/ts';
@foreach($data->typeImports as $importPath => $typeNames)
import type { {!! implode(', ', $typeNames) !!} } from '{!! $importPath !!}';
@endforeach
@foreach($data->actions as $action)
@if(is_string($action['pageType'] ?? null))
export type {!! ucfirst($action['methodName']) !!}PageProps = {!! $action['pageType'] !!};
@endif
@endforeach

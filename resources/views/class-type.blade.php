@foreach ($data->typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach
@if(count($data->typeImports) > 0)

@endif
export interface {{ $data->typeName }} extends {!! implode(', ', $data->extends) !!} {}

@if ($usesTolkiPackage && count($valueImports) > 0)
import { type AsEnum } from '@tolki/ts';
@endif
@foreach ($valueImports as $path => $names)
import { {{ implode(', ', $names) }} } from '{{ $path }}';
@endforeach
@foreach ($typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@endforeach
@if (count($valueImports) > 0 || count($typeImports) > 0)

@endif
declare global {
    namespace Inertia {
        type SharedData = {!! $sharedPageProps !!};
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {!! $sharedPageProps !!};
@if($withAllErrors)
        errorValueType: string[];
@endif
    }
}

export {};

@foreach ($typeImports as $path => $types)
import type { {{ implode(', ', $types) }} } from '{{ $path }}';
@if ($loop->last)

@endif
@endforeach
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

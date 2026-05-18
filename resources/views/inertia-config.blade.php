@foreach($importStatements as $import)
{!! $import !!}
@endforeach
@if(count($importStatements) > 0)

@endif
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {!! $sharedPageProps !!};
@if($withAllErrors)
        errorValueType: string[];
@endif
    }
}

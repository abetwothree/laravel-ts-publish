@if($isEmpty)
export {};
@else
@foreach($imports as $import)
{!! $import !!}
@endforeach

export type BroadcastEvent =
@foreach($events as $event)
    | "{{ $event['broadcastName'] }}"{{ $loop->last ? ';' : '' }}
@endforeach

export const BroadcastEvents = Object.freeze({
@foreach($events as $event)
    {!! $event['constKey'] !!}: "{{ $event['broadcastName'] }}",
@endforeach
} as const);

export type {
    {!! implode(",\n    ", $eventNames) !!}
};
@endif

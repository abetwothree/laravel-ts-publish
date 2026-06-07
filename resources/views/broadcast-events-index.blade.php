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

export const BroadcastEvents = {
@foreach($events as $event)
    {!! $event['constKey'] !!}: "{{ $event['broadcastName'] }}" as const,
@endforeach
} as const;

export type { {!! implode(', ', $eventNames) !!} };
@endif

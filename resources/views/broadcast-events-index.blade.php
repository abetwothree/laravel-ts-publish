@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@if($isEmpty)
export {};
@else
@foreach($imports as $import)
{!! $import !!}
@endforeach

export type BroadcastEvent =
@foreach($events as $event)
    | {!! LaravelTsPublish::toJsLiteral($event['broadcastName']) !!}{{ $loop->last ? ';' : '' }}
@endforeach

export const BroadcastEvents = Object.freeze({
@foreach($events as $event)
    {!! $event['constKey'] !!}: {!! LaravelTsPublish::toJsLiteral($event['broadcastName']) !!},
@endforeach
} as const);

export type {
    {!! implode(",\n    ", $eventNames) !!}
};
@endif

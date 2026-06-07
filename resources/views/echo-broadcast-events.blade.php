@foreach($imports as $import)
{!! $import !!}
@endforeach

declare module "{{ $echoPackage }}" {
    interface Events {
@foreach($events as $event)
        "{{ $event['broadcastName'] }}": {{ $event['eventName'] }};
@endforeach
    }
}

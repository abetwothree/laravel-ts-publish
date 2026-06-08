@use('AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish')
@foreach($imports as $import)
{!! $import !!}
@endforeach

declare module "{{ $echoPackage }}" {
    interface Events {
@foreach($events as $event)
        {!! LaravelTsPublish::validJsObjectKey($event['broadcastName']) !!}: {{ $event['eventName'] }};
@endforeach
    }
}

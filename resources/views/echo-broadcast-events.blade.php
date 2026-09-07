@use('AbeTwoThree\LaravelTsPublish\Facades\JsEmitter')
@foreach($imports as $import)
{!! $import !!}
@endforeach

declare module "{{ $echoPackage }}" {
    interface Events {
@foreach($events as $event)
        {!! JsEmitter::validJsObjectKey($event['broadcastName']) !!}: {{ $event['exportedName'] }};
@endforeach
    }
}

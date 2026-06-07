@if($data->isEmpty)
export {};
@else
{!! $data->typeUnion !!}

export const BroadcastChannels = {
{!! $data->constBody !!}
};
@endif

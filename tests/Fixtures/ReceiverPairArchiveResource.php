<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource that reads each accessor of ReceiverPairArchive, and makes some of the same reads itself: as a
 * property's whole value (`direct_`), and under a key of an inline array that another key follows (`keyed_`).
 *
 * @mixin ReceiverPairArchive
 */
class ReceiverPairArchiveResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'owners_of_either' => $this->owners_of_either,
            'owner_list_of_either' => $this->owner_list_of_either,
            'owners_of_mixed' => $this->owners_of_mixed,
            'owner_list_of_mixed' => $this->owner_list_of_mixed,
            'owners_or_null' => $this->owners_or_null,
            'owner_list_or_null' => $this->owner_list_or_null,
            'owners_alone' => $this->owners_alone,
            'owners_or_label' => $this->owners_or_label,
            'owners_or_watchers' => $this->owners_or_watchers,
            'owners_or_crm_watchers' => $this->owners_or_crm_watchers,
            'both_watchers_or_owners' => $this->both_watchers_or_owners,
            'owners_or_receiver_or_third' => $this->owners_or_receiver_or_third,
            'owners_or_third_watchers_or_receiver' => $this->owners_or_third_watchers_or_receiver,
            'owners_or_crm_watchers_or_third_watchers' => $this->owners_or_crm_watchers_or_third_watchers,
            'owners_or_receiver_then_third' => $this->owners_or_receiver_then_third,
            'owners_or_sender_then_third' => $this->owners_or_sender_then_third,
            'third_or_else_receiver_or_else_owners' => $this->third_or_else_receiver_or_else_owners,
            'direct_owners_or_crm_watchers' => $request->boolean('x') ? (new ReceiverPairDirectory)->owners : $this->crmWatchers,
            'direct_both_watchers_or_owners' => $request->boolean('x')
                ? $this->watchers
                : ($request->boolean('y') ? $this->crmWatchers : (new ReceiverPairDirectory)->owners),
            'direct_when_pair_or_receiver' => $request->boolean('y')
                ? $this->when($request->boolean('x'), $this->sender, $this->receiver)
                : $this->receiver,
            'direct_watchers_then_owners_or_sender' => $request->boolean('x')
                ? $this->watchers
                : ($request->boolean('y') ? (new ReceiverPairDirectory)->owners : $this->sender),
            'direct_crm_watchers_then_owners_or_sender' => $request->boolean('x')
                ? $this->crmWatchers
                : ($request->boolean('y') ? (new ReceiverPairDirectory)->owners : $this->sender),
            'direct_watchers_then_when_owners_or_sender' => $request->boolean('x')
                ? $this->watchers
                : $this->when($request->boolean('y'), (new ReceiverPairDirectory)->owners, $this->sender),
            'keyed_owners_or_sender' => [
                'either' => $request->boolean('x') ? (new ReceiverPairDirectory)->owners : $this->sender,
                'first' => $this->sender,
            ],
            'keyed_owners_or_crm_watchers' => [
                'either' => $request->boolean('x') ? (new ReceiverPairDirectory)->owners : $this->crmWatchers,
                'first' => $this->sender,
            ],
            'keyed_watchers_then_owners_or_sender' => [
                'either' => $request->boolean('x')
                    ? $this->watchers
                    : ($request->boolean('y') ? (new ReceiverPairDirectory)->owners : $this->sender),
                'second' => $this->receiver,
            ],
        ];
    }
}

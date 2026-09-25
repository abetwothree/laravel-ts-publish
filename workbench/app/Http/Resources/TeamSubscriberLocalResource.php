<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\SubscribedTeam;
use Workbench\App\Models\Team;

/**
 * A local assigned from an `instanceof` ternary whose proven arm reads a relation only the subclass declares keeps the
 * narrowing for every read through it, as the same ternary written inline does. `$unproven` and `$negatedTrueArm`
 * read that relation in the arm the test does not prove, so nothing narrows them.
 *
 * @mixin Team
 */
final class TeamSubscriberLocalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscriber = $this->resource instanceof SubscribedTeam ? $this->resource->subscriber : null;
        $negated = ! $this->resource instanceof SubscribedTeam ? null : $this->resource->subscriber;
        $team = $this->resource;
        $viaLocal = $team instanceof SubscribedTeam ? $team->subscriber : null;
        $unproven = $this->resource instanceof SubscribedTeam ? null : $this->resource->subscriber;
        $negatedTrueArm = ! $this->resource instanceof SubscribedTeam ? $this->resource->subscriber : null;

        return [
            'subscriber_name' => $subscriber?->name,
            'subscriber_id' => $subscriber?->id,
            'inline_name' => $this->resource instanceof SubscribedTeam ? $this->resource->subscriber?->name : null,
            'negated_name' => $negated?->name,
            'via_local_email' => $viaLocal?->email,
            'unproven_name' => $unproven?->name,
            'negated_true_arm_name' => $negatedTrueArm?->name,
        ];
    }
}

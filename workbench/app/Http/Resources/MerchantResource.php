<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Merchant;

/**
 * Exercises Model::toResource() / Collection::toResourceCollection() resolution: naming
 * convention, #[UseResource], explicit arguments, the unresolvable negative cases, and
 * (registrar/registrars/suppliers) the three resolution orderings against a losing
 * candidate that also exists, so an inverted order would visibly fail.
 *
 * The unpublished_guess pair covers the published-set gate: the guessed AttachmentResource and
 * AttachmentCollection both exist but are #[TsExclude]d, so neither is ever written and the
 * convention branches must reject them (see PublishedResourceRegistry).
 *
 * Also reuses the staff/registrars/historyEvent relations for the ->map->only()/->except()
 * HigherOrderCollectionProxy: a to-many whenLoaded param is a bound collection and matches,
 * a singular one (historyEvent) is not and must stay unknown.
 *
 * @mixin Merchant
 */
#[TsResource(model: Merchant::class)]
class MerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_via_closure' => $this->whenLoaded('owner', fn ($m) => $m->toResource()),
            'owner_explicit' => $this->whenLoaded('owner', fn ($m) => $m->toResource(UserResource::class)),
            // Negative: a non-::class constant on a resource class must not resolve as that
            // resource — resolveClassConstArgument() only reads the ::class pseudo-constant.
            'owner_variant_constant' => $this->whenLoaded('owner', fn ($m) => $m->toResource(UserResource::ADMIN_VARIANT)),
            'owner_direct' => $this->owner->toResource(),
            'staff_via_closure' => $this->whenLoaded('staff', fn ($rows) => $rows->toResourceCollection()),
            'staff_explicit' => $this->whenLoaded('staff', fn ($rows) => $rows->toResourceCollection(UserResource::class)),
            'history_event' => $this->whenLoaded('historyEvent', fn ($m) => $m->toResource()),
            // Negative: filing and alert do not have resource classes, so the output is unknown
            'filing' => $this->whenLoaded('filing', fn ($m) => $m->toResource()),
            'alert' => $this->whenLoaded('alert', fn ($m) => $m->toResource()),
            'registrar' => $this->whenLoaded('registrar', fn ($m) => $m->toResource()),
            // #[UseResourceCollection] names a collection with its own toArray() and no resource: its type, not unknown
            'registrars' => $this->whenLoaded('registrars', fn ($rows) => $rows->toResourceCollection()),
            'suppliers' => $this->whenLoaded('suppliers', fn ($rows) => $rows->toResourceCollection()),
            // Negative: AttachmentResource / AttachmentCollection exist but are #[TsExclude]d, so
            // no .ts file is written for either. A convention guess must not name a resource this
            // run never emits — the import would point at a module that does not exist.
            'unpublished_guess' => $this->whenLoaded('attachment', fn ($m) => $m->toResource()),
            'unpublished_guess_collection' => $this->whenLoaded('attachments', fn ($rows) => $rows->toResourceCollection()),
            // ->map->only(): the HigherOrderCollectionProxy on a bound to-many closure param —
            // element shape, array-wrapped, with a nullable column and an enum column.
            'staff_map_only' => $this->whenLoaded('staff', fn ($rows) => $rows->map->only(['id', 'name', 'role', 'last_login_at'])),
            // ->map->except(): same proxy, the complement variant, on a small element model.
            'registrars_map_except' => $this->whenLoaded('registrars', fn ($rows) => $rows->map->except(['id'])),
            // Negative: historyEvent is singular (BelongsTo), so $m binds to varModelBindings,
            // not varCollectionBindings — the proxy must not match and stays unknown.
            'history_event_map_only' => $this->whenLoaded('historyEvent', fn ($m) => $m->map->only(['status'])),
        ];
    }
}

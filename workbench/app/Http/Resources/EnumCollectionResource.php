<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * Exercises EnumResource::collection() across its backing shapes: an accessor returning
 * list<Enum>, an AsEnumCollection cast, a first-class callable value, and a local
 * variable ->map() (not $this->relation->map()) — those should all emit an array-wrapped
 * AsEnum utility type, not the unresolved EnumResource itself. member_role_snapshot is the
 * one exception: a bare (unwrapped) enum read, pinning a distinct import-GC concern.
 *
 * @mixin Team
 */
class EnumCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Accessor-backed: Attribute<list<Status>, never>
            'status_history' => EnumResource::collection($this->status_history),

            // AsEnumCollection cast, nullable column
            'week_days' => EnumResource::collection($this->week_days),

            // Inline array: the [] suffix must survive analyzeInlineArray()'s AsEnum rewrite too.
            'wrapped_week_days' => [
                'week_days' => EnumResource::collection($this->week_days),
            ],

            // whenHas() never resolves its value argument for its own type — it re-derives the
            // type from the named attribute directly — but IS checked for EnumResource shape, so
            // this first-class-callable value still gets the AsEnum rewrite, not the raw type.
            'week_days_when_has' => $this->whenHas('week_days', EnumResource::collection(...)),

            // An explicit default arm unions in an extra type the old AsEnum<typeof X>[] rebuild
            // couldn't reproduce. ResourceTransformer::rewriteEnumResourceTypes() now substitutes
            // the bare enum token in place instead of rebuilding, so the array suffix, the `| null`,
            // and the default's own `| string` arm all survive alongside the AsEnum wrap.
            'week_days_when_has_default' => $this->whenHas('week_days', EnumResource::collection(...), 'none'),

            // Same mechanism via whenAppended(), with an ordinary (non-first-class-callable)
            // value: whenAppended() never forwards the attribute value to a Closure, so the FCC
            // form isn't reachable there in practice, but this eagerly-evaluated form is.
            // status_history (an accessor, not a cast column) is the realistic pairing here:
            // whenAppended() surfaces $appends entries, which are accessor-computed by nature.
            'status_history_when_appended' => $this->whenAppended('status_history', EnumResource::collection($this->status_history)),

            // Local variable ->map(), distinct from $this->relation->map(): the outer whenLoaded
            // closure captures $members, which then calls ->map() on a bare variable.
            'members_via_var' => $this->whenLoaded(
                'members',
                fn ($members) => $members->map(fn (User $member) => EnumResource::make($member->role))
            ),

            // A bare (unwrapped) enum read nested inside an inline array — the only other Role
            // reader in this file, members_via_var, is EnumResource-wrapped. Pins that this
            // property's own RoleType import survives, and registers in propertyInlineEnumFqcns
            // (see ResourceTransformer::rewriteEnumResourceTypes()'s import-GC).
            'member_role_snapshot' => $this->whenLoaded(
                'members',
                fn ($members) => $members->map(fn (User $member) => ['role' => $member->role])
            ),

            // Mixed ternary nested inside an inline array literal: one arm wraps status_history
            // (array-shaped), the other reads latest_status directly (scalar) — same Status FQCN,
            // different shapes. Exercises analyzeInlineArray()'s $isMixed synthesis (Task 14).
            'wrapped_status_fallback' => [
                'status' => $this->is_active
                    ? EnumResource::collection($this->status_history)
                    : $this->latest_status,
            ],

            // Top-level counterpart to wrapped_status_fallback, roles swapped: EnumResource::make()
            // (scalar) first, the array-shaped status_history read directly second — same Status
            // FQCN, outside any inline array. Exercises ResourceTransformer::rewriteEnumResourceTypes()'s
            // own $isMixed reconstruction (Task 16), not analyzeInlineArray()'s.
            'latest_status_or_history' => $this->is_active
                ? EnumResource::make($this->latest_status)
                : $this->status_history,

            // Mixed ternary, wrap arm first this time and a collection rather than a scalar make():
            // the [] belongs on the wrap arm here, not the direct one. Exercises the general case
            // rewriteEnumResourceTypes()'s $isMixed branch previously assumed away (Task 28).
            'wrapped_history_or_scalar' => $request->boolean('wrap')
                ? EnumResource::collection($this->status_history)
                : $this->latest_status,

            // Both arms are independently array-shaped, so their raw type strings dedupe to one
            // merged union member — only per-arm tracking still tells rewriteEnumResourceTypes()
            // the [] belongs on both, not just whichever arm the merged string happened to keep.
            'wrapped_history_or_array' => $request->boolean('wrap')
                ? EnumResource::collection($this->status_history)
                : $this->status_history,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * Exercises the conditional family's default argument. An explicit default means the key is always
 * present, so the property is required; the default's own type unions into the emitted type when it
 * resolves, and the value arm's type stands alone when it does not.
 *
 * @mixin Address
 */
class ConditionalDefaultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'not_null_no_default' => $this->whenNotNull($this->full_address),
            'not_null_with_default' => $this->whenNotNull($this->full_address, 0),
            'not_null_same_type_default' => $this->whenNotNull($this->latitude, 0),
            'null_with_default' => $this->whenNull($this->full_address, 'absent'),

            // hasExplicitDefaultArg() contract: an explicit `null` at the default position still counts —
            // Laravel distinguishes it from an omitted argument via func_num_args(), not `=== null`.
            'not_null_explicit_null_default' => $this->whenNotNull($this->full_address, null),

            // A named default is read by name: Laravel's func_num_args() sees it as a second argument, so
            // it is a real default — required, and unioned in — exactly like the positional form above.
            'not_null_named_default' => $this->whenNotNull($this->full_address, default: 0),

            // A spread argument at the default position makes the count unknowable, so it bails out.
            'not_null_spread_default' => $this->whenNotNull($this->full_address, ...[0]),

            'when_no_default' => $this->when($this->id > 0, $this->full_address),

            // A differently-typed default makes the union observable: the merged type must be
            // `string | number`, not just `string` — a same-typed default can't tell union logic
            // apart from a no-op, since deduping collapses it back to a single member either way.
            'when_with_default' => $this->when($this->id > 0, $this->full_address, 0),

            // Every with-default case below pairs a value arm with a *differently*-typed default, so the
            // union is observable: a handler that only flipped `optional` would emit the value arm alone.
            'has_with_default' => $this->whenHas('full_address', $this->full_address, 0),
            'loaded_with_default' => $this->whenLoaded('user', fn ($user) => $user, null),
            'counted_with_default' => $this->whenCounted('user', null, 'none'),

            // whenAggregated's default sits at index 4 — the riskiest index in the family, since it's
            // the only one not at position 2 or 3. Neither this handler nor the pivot ones below inspect
            // the relation/table/aggregate arguments at all (their type is a fixed 'number'/'unknown'),
            // so no real aggregate or pivot relation needs to exist on the model for this to be meaningful.
            'aggregated_no_default' => $this->whenAggregated('items', 'price', 'sum', null),
            'aggregated_with_default' => $this->whenAggregated('items', 'price', 'sum', null, 'none'),

            // whenPivotLoaded's default sits at index 2. A pivot value arm is a hard-coded `unknown`,
            // which already covers the default, so the union collapses back to `unknown` — required.
            'pivot_loaded_no_default' => $this->whenPivotLoaded('team_user', null),
            'pivot_loaded_with_default' => $this->whenPivotLoaded('team_user', null, 0),

            // whenPivotLoadedAs's default sits at index 3 — one higher than whenPivotLoaded, because of
            // its leading $accessor argument.
            'pivot_loaded_as_no_default' => $this->whenPivotLoadedAs('membership', 'team_user', null),
            'pivot_loaded_as_with_default' => $this->whenPivotLoadedAs('membership', 'team_user', null, 0),

            'unless_no_default' => $this->unless($this->id > 0, $this->full_address),
            'unless_with_default' => $this->unless($this->id > 0, $this->full_address, 0),
            'appended_no_default' => $this->whenAppended('full_address'),
            'appended_with_default' => $this->whenAppended('full_address', $this->full_address, 0),
            'exists_no_default' => $this->whenExistsLoaded('user'),
            'exists_with_default' => $this->whenExistsLoaded('user', null, 'absent'),

            // transform() types from the callback's return, not $value's — the callback here returns a
            // boolean while $value (full_address) is string|null, so a wrong implementation would show up.
            'transform_no_default' => $this->transform($this->full_address, fn (string $address): bool => $address !== ''),
            'transform_with_default' => $this->transform($this->full_address, fn (string $address): bool => $address !== '', 0),

            // transform()'s default is invoked via the global transform() helper's $default($value) — one
            // argument — unlike the rest of the family's zero-argument value($default), so a one-parameter
            // closure default runs cleanly and its arm must union in.
            'transform_with_one_param_default' => $this->transform(
                $this->full_address,
                fn (string $address): bool => $address !== '',
                fn (string $address): int => strlen($address),
            ),

            // Nested resource constructors wrapping a conditional must be optional: the inner call can
            // produce a MissingValue. StaticCall and New_ take separate detection paths.
            'unless_user_resource' => UserResource::make($this->unless($this->id > 0, $this->user)),
            'transform_user_resource' => new UserResource($this->transform($this->user, fn ($user) => $user)),

            // mergeUnless mirrors mergeWhen's dispatch (index 1, always optional). If the dispatch were
            // wrong, this key would be silently absent from the output rather than typed as unknown.
            $this->mergeUnless($this->id > 0, [
                'merge_unless_label' => $this->full_address,
            ]),
        ];
    }
}

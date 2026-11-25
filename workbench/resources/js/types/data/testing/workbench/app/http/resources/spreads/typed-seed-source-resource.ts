/**
 * A map method a subclass may override, spread over earlier keys, and other objects' seeded maps (GH-161 C2, Q-A).
 *
 * @see Workbench\App\Http\Resources\Spreads\TypedSeedSourceResource
 */
export interface TypedSeedSourceResource
{
    overridable_map_given_key: { by: { name: string | null; email: string | null }; z: number };
    overridable_map_local: { by: { name: string | null; email: string | null } | string; z: number | string };
    overridable_map_spread: { by: { name: string | null; email: string | null } | string; z: number | string };
    seed_from_other_call: { seen: boolean; [key: string]: unknown };
    seed_from_property: { seen: boolean; [key: string]: unknown };
    seed_from_coalesced: { seen: boolean; [key: string]: unknown };
    seed_from_static_call: { seen: boolean; [key: string]: unknown };
    seed_from_other_call_nested: { row: unknown };
}

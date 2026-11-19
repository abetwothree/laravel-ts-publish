/**
 * Literals whose discriminant code changes through what a call dispatches to: parent::unless(), which calls the
 * when() this resource overrides to unpin the post, and the `tick` get hook TickingResource declares, whose
 * `self::clearTick()` runs the parent's clearTick(), not the one here. Each literal publishes flat, because PHP can
 * send a value no arm admits (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\DispatchOverridesResource
 */
export interface DispatchOverridesResource
{
    parent_unless: { is_pinned: boolean; note: string; detail: string | null };
    parent_hook_self: { is_pinned: boolean; tick: boolean; detail: string | null };
}

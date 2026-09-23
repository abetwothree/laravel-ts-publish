/**
 * A nullsafe call on a morphTo whose methods the resource's own model also has. Only the relation's generic says
 * what the relation holds, so `MorphTo<Model, $this>` stays unknown and `MorphTo<Crew|Squad, $this>` types.
 *
 * @see Workbench\App\Http\Resources\RosterSlotResource
 */
export interface RosterSlotResource
{
    id: number;
    assignable_label: unknown;
    assignable_title: unknown;
    assignee_label: string | null;
    assignee_title: string | null;
}

/**
 * One model a RosterSlot's assignee can be.
 *
 * @see Workbench\App\Models\Crew
 */
export interface Crew
{
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}

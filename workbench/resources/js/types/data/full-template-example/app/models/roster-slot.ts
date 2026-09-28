import type { Crew, Squad } from '.';

/**
 * Fixture: one morphTo read through two generics. `assignable` names only Model and no model declares the inverse;
 * `assignee` names the models the column can hold.
 *
 * @see Workbench\App\Models\RosterSlot
 */
export interface RosterSlot
{
    // Columns
    id: number;
    assignable_type: string;
    assignable_id: number;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    assignable: unknown;
    assignee: Crew | Squad;
    // Counts
    assignable_count: number;
    assignee_count: number;
    // Exists
    assignable_exists: boolean;
    assignee_exists: boolean;
}

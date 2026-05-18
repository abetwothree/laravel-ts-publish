import type { TaskOwner } from '.';

/**
 * @see Workbench\App\Models\StrictTaskAssignment
 */
export interface StrictTaskAssignment
{
    // Columns
    id: number;
    title: string;
    team_id: number;
    category_id: number;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    assignee: TaskOwner;
    // Counts
    assignee_count: number;
    // Exists
    assignee_exists: boolean;
}

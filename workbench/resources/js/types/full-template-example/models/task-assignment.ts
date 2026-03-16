import type { TaskOwner } from './';

export interface TaskAssignment
{
    // Columns
    id: number;
    title: string;
    team_id: number;
    category_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    assignee: TaskOwner | null;
    // Counts
    assignee_count: number;
    // Exists
    assignee_exists: boolean;
}

import type { TaskAssignment } from '.';

/**
 * @see Workbench\App\Models\TaskOwner
 */
export interface TaskOwner
{
    // Columns
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    password: string;
    options: string | null;
    remember_token: string | null;
    created_at: string | null;
    updated_at: string | null;
    role: string | null;
    membership_level: string | null;
    phone: string | null;
    avatar: string | null;
    bio: string | null;
    settings: string | null;
    last_login_at: string | null;
    last_login_ip: string | null;
    // Relations
    task_assignments: TaskAssignment[];
    // Counts
    task_assignments_count: number;
    // Exists
    task_assignments_exists: boolean;
}

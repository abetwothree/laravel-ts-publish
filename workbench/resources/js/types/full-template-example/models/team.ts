import type { User } from './';

export interface Team
{
    // Columns
    id: number;
    name: string;
    slug: string;
    description: string | null;
    owner_id: number;
    is_active: boolean;
    settings: unknown[] | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    // Mutators
    /** Whether the team has any members */
    has_member: boolean;
    /** Number of members */
    member_count: number;
    // Relations
    /** The user who owns this team */
    owner: User;
    /** Members of the team (pivot includes role and joined_at) */
    members: User[];
    // Counts
    owner_count: number;
    members_count: number;
    // Exists
    owner_exists: boolean;
    members_exists: boolean;
}

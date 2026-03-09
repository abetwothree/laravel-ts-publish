import { User } from '.';

export interface Team
{
    id: number;
    name: string;
    slug: string;
    description: string | null;
    owner_id: number;
    is_active: boolean;
    settings: Array<unknown> | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
}

export interface TeamMutators
{
    has_member: boolean;
    member_count: number;
}

export interface TeamRelations
{
    // Relations
    owner: User;
    members: User[];
    // Counts
    owner_count: number;
    members_count: number;
    // Exists
    owner_exists: boolean;
    members_exists: boolean;
}

export interface TeamAll extends Team, TeamMutators, TeamRelations {}

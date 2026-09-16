import { type AsEnum } from '@tolki/ts';

import { Status, WeekDays } from '../enums';
import type { StatusType, WeekDaysType } from '../enums';
import type { User } from '.';

/**
 * A Team read through a subclass that knows one more relation — the narrowing target.
 *
 * `subscriber()` is deliberately a third relation on `owner_id`, distinct from Team's own `owner()`
 * and `map()`: TeamSubscriberResource asserts that narrowing reaches a relation only the subclass knows.
 *
 * @see Workbench\App\Models\SubscribedTeam
 */
export interface SubscribedTeam
{
    id: number;
    name: string;
    slug: string;
    description: string | null;
    owner_id: number;
    is_active: boolean;
    settings: Record<string, unknown> | null;
    grid_config: { filters?: Record<string, unknown>; sorts?: string[]; columns?: string[] } | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    week_days: WeekDaysType[] | null;
    grid_configs: { label: string; config: Record<string, unknown> }[] | null;
    grid_preset: { name: string; locked?: boolean } | null;
}

export interface SubscribedTeamResource extends Omit<SubscribedTeam, 'week_days'>
{
    week_days: AsEnum<typeof WeekDays>[] | null;
}

export interface SubscribedTeamMutators
{
    /** Whether the team has any members */
    has_member: boolean;
    /** Number of members */
    member_count: number;
    status_history: StatusType[];
    /** A single scalar Status, distinct from statusHistory()'s array shape. */
    latest_status: StatusType;
}

export interface SubscribedTeamMutatorsResource extends Omit<SubscribedTeamMutators, 'status_history' | 'latest_status'>
{
    status_history: AsEnum<typeof Status>[];
    latest_status: AsEnum<typeof Status>;
}

export interface SubscribedTeamRelations
{
    // Relations
    /** The user subscribed to this team */
    subscriber: User;
    /** The user who owns this team */
    owner: User;
    /** Named literally 'map' to pin the relation-filter guard against Laravel's ->map proxy. */
    map: User;
    /** Members of the team (pivot includes role and joined_at) */
    members: User[];
    // Counts
    subscriber_count: number;
    owner_count: number;
    map_count: number;
    members_count: number;
    // Exists
    subscriber_exists: boolean;
    owner_exists: boolean;
    map_exists: boolean;
    members_exists: boolean;
}

export interface SubscribedTeamAll extends SubscribedTeam, SubscribedTeamMutators, SubscribedTeamRelations {}

export interface SubscribedTeamAllResource extends SubscribedTeamResource, SubscribedTeamMutatorsResource, SubscribedTeamRelations {}

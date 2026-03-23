import type { MembershipLevelType, RoleType } from '../../enums';

/** Represents a user loaded through a team's belongsToMany pivot. Exercises: whenPivotLoaded, whenPivotLoadedAs, whenHas on enum attributes. */
export interface TeamMemberResource
{
    id: number;
    name: string;
    email: string;
    role?: RoleType | null;
    membership_level?: MembershipLevelType | null;
    avatar?: string | null;
    team_role?: unknown;
    joined_at?: unknown;
    subscription_role?: unknown;
}

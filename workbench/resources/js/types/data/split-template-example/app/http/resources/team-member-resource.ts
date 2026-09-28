import type { MembershipLevelType, RoleType } from '../../enums';

/**
 * Represents a user loaded through a team's belongsToMany pivot.
 *
 * Exercises: whenPivotLoaded, whenPivotLoadedAs, whenHas on enum attributes.
 *
 * @see Workbench\App\Http\Resources\TeamMemberResource
 */
export interface TeamMemberResource
{
    id: number;
    name: string;
    email: string;
    role?: RoleType | null;
    membership_level?: MembershipLevelType | null;
    role_via_value?: RoleType | null;
    avatar?: string;
    team_role?: unknown;
    joined_at?: unknown;
    subscription_role?: unknown;
}

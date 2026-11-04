import { type AsEnum } from '@tolki/ts';

import { Role } from '../../enums';
import type { RoleType } from '../../enums';
import type { User } from '../../models';

/**
 * Exercises collection method chains rooted at a many-relation
 * ($this->members->take(5)->map(...)->values()), plus the ->map->only()
 * HigherOrderCollectionProxy reached directly off the relation.
 *
 * @see Workbench\App\Http\Resources\RelationChainResource
 */
export interface RelationChainResource
{
    first_members: User[];
    member_cards: { id: number; name: string }[];
    member_profiles: { id: number; role: RoleType | null; owner: User }[];
    member_map_only: { id: number; role: RoleType | null }[];
    member_emails: string[];
    member_roles: (RoleType | null)[];
    member_role_resources: AsEnum<typeof Role>[];
    member_role_resources_filtered: AsEnum<typeof Role>[] | Record<string, AsEnum<typeof Role>>;
    wrapped_filtered: { roles: AsEnum<typeof Role>[] | Record<string, AsEnum<typeof Role>> };
    member_names_upper: string[];
    member_formatted: string[];
    member_mapped_fcc: unknown;
    member_plucked_fcc: unknown;
    first_member: User | null;
    members_after_load: User[];
    first_member_after_load: User | null;
    members_sorted: User[] | Record<string, User>;
    members_filtered_cards: { id: number }[] | Record<string, { id: number }>;
    members_tail: User[] | Record<string, User>;
    members_tail_values: User[];
    members_sliced_emails: string[] | Record<string, string>;
    members_keyed_by_id: string[] | Record<string, string>;
    members_skipped: User[];
}

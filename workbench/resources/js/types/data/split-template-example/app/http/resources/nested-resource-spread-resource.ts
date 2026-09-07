import type { User } from '../../models';
import type { ProfileResource, TeamMemberResource, UserResource } from '.';

/**
 * Exercises spreading a resolved resource inside a NESTED inline array literal — a map()
 * closure's return body — as opposed to the four already-supported top-level toArray() spreads.
 *
 * Mirrors a real-world report: `whenLoaded()` closure -> `$var->map(closure)` -> multi-statement
 * inner closure -> array literal spreading `SomeResource::make($x)->resolve($request)` plus a
 * sibling key. Every layer except the spread-plus-siblings shape already works.
 *
 * @see Workbench\App\Http\Resources\NestedResourceSpreadResource
 */
export interface NestedResourceSpreadResource
{
    id: number;
    members_with_profile?: (Omit<UserResource, 'profile'> & { profile: ProfileResource })[];
    members_bare?: UserResource[];
    members_model_spread?: (Omit<User, 'flag'> & { flag: boolean })[];
    members_collection_spread?: Record<number, User> & { flag: boolean };
    members_double_spread?: (Omit<UserResource, 'note' | keyof ProfileResource> & Omit<ProfileResource, 'note'> & { note: string })[];
    members_with_profile_untyped?: (Omit<UserResource, 'profile'> & { profile: ProfileResource })[];
    owner_map_untyped?: unknown;
    members_colliding_spread?: (Omit<UserResource, keyof TeamMemberResource> & TeamMemberResource)[];
    members_model_then_resource_spread?: (Omit<User, 'flag' | keyof UserResource | keyof User> & Omit<UserResource, 'flag' | keyof User> & Omit<User, 'flag'> & { flag: boolean })[];
    members_resource_then_model_spread?: (Omit<UserResource, 'flag' | keyof User | keyof UserResource> & Omit<User, 'flag' | keyof UserResource> & Omit<UserResource, 'flag'> & { flag: boolean })[];
    owner_relation_spread: Omit<User, 'flag'> & { flag: boolean };
}

import type { TeamMemberResource, UserResource } from './';

/** Exercises: when, whenLoaded + Resource::make, Resource::collection, whenCounted, mergeWhen. */
export interface TeamResource
{
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    is_active: boolean;
    owner?: UserResource;
    members?: TeamMemberResource[];
    members_count?: number;
    settings?: unknown[];
}

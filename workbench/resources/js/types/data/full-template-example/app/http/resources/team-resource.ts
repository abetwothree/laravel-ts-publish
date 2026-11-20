import type { TeamMemberResource, UserResource } from '.';

/**
 * Exercises: when, whenLoaded + Resource::make, Resource::collection,
 * whenCounted, mergeWhen.
 *
 * @see Workbench\App\Http\Resources\TeamResource
 */
export type TeamResource = {
    id: number;
    name: string;
    slug: string;
    owner?: UserResource;
    members?: TeamMemberResource[];
    members_count?: number;
    settings?: Record<string, unknown> | null;
} & (
    | { description: string | null; is_active: true }
    | { description?: never; is_active: false }
);

import type { TeamMemberResource, UserResource } from '.';

/**
 * Singular resource whose ::collection() preserves keys via $preserveKeys — exercises the
 * anonymous (static-collection) Inertia path. Delegates to TeamResource's toArray() shape.
 *
 * @see Workbench\App\Http\Resources\PreserveKeysTeamResource
 */
export type PreserveKeysTeamResource = {
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

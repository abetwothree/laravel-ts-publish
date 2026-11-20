import type { TeamMemberResource, UserResource } from '.';

/**
 * Declares no toArray() of its own — pins that the analyzer walks up to the nearest ancestor
 * that does, rather than emitting an empty interface. Carries its own @mixin.
 *
 * @see Workbench\App\Http\Resources\BodylessTeamResource
 */
export type BodylessTeamResource = {
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

import type { User } from '../../models';

/**
 * A closure parameter that shadows a top-level local resolves through its own scoped binding
 * (whenLoaded relation / relation-chain element model) for the closure's body only, and must not
 * leak the outer local's value. Outside the closure, `outer_member` proves the shadowing param no
 * longer suppresses the top-level `$member` local's own binding.
 *
 * @see Workbench\App\Http\Resources\ClosureParamShadowResource
 */
export interface ClosureParamShadowResource
{
    outer_member: string;
    mapped_members: User[];
    loaded_owner?: User;
    loaded_members_bare?: User[];
    shadowed: string;
}

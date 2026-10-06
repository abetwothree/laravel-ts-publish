import type { RoleType } from '../../enums';
import type { EventLogResource, RegistrarResource, SupplierSummaryResource, UserResource } from '.';

/**
 * Exercises Model::toResource() / Collection::toResourceCollection() resolution: naming
 * convention, #[UseResource], explicit arguments, the unresolvable negative cases, and
 * (registrar/registrars/suppliers) the three resolution orderings against a losing
 * candidate that also exists, so an inverted order would visibly fail.
 *
 * The unpublished_guess pair covers the published-set gate: the guessed AttachmentResource and
 * AttachmentCollection both exist but are #[TsExclude]d, so neither is ever written and the
 * convention branches must reject them (see PublishedResourceRegistry).
 *
 * Also reuses the staff/registrars/historyEvent relations for the ->map->only()/->except()
 * HigherOrderCollectionProxy: a to-many whenLoaded param is a bound collection and matches,
 * a singular one (historyEvent) is not and must stay unknown.
 *
 * @see Workbench\App\Http\Resources\MerchantResource
 */
export interface MerchantResource
{
    id: number;
    owner_via_closure?: UserResource | null;
    owner_explicit?: UserResource | null;
    owner_variant_constant?: unknown;
    owner_direct: UserResource;
    staff_via_closure?: UserResource[];
    staff_explicit?: UserResource[];
    history_event?: EventLogResource | null;
    filing?: unknown;
    alert?: unknown;
    registrar?: RegistrarResource | null;
    registrars?: unknown;
    suppliers?: SupplierSummaryResource[];
    unpublished_guess?: unknown;
    unpublished_guess_collection?: unknown;
    staff_map_only?: ({ id: number; name: string; role: RoleType | null; last_login_at: string | null })[];
    registrars_map_except?: { name: string }[];
    history_event_map_only?: unknown;
}

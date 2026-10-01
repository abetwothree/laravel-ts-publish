import type { Facility } from '../models';
import type { AuditTrail } from '../packages/audit/models';

/** @see Workbench\App\Events\FacilityAudited */
export interface FacilityAudited {
    facility: Partial<Facility>;
    trail: Partial<AuditTrail>;
    record: unknown;
}

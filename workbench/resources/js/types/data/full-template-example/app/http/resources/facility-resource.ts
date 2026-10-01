import type { AuditTrail } from '../../packages/audit/models';

/**
 * Reads a relation whose model is published on demand, and one whose model is never published.
 *
 * @see Workbench\App\Http\Resources\FacilityResource
 */
export interface FacilityResource
{
    id: number;
    name: string;
    audit_trails: AuditTrail[];
    latest_trail: AuditTrail | null;
    excluded_records: unknown;
    first_excluded: unknown;
    summary: { trails: AuditTrail[]; excluded: unknown };
}

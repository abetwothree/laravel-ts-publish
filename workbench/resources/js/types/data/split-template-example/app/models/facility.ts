import type { AuditInspector, AuditTrail } from '../packages/audit/models';
import type { User } from '.';

/**
 * Relates to a model outside every configured model directory, which is published on demand, and to a
 * #[TsExclude]d model, whose relation is left out.
 *
 * @see Workbench\App\Models\Facility
 */
export interface Facility
{
    id: number;
    name: string;
    inspector_type: string | null;
    inspector_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface FacilityMutators
{
    /** Names a model that is never published, so it has no file to import. */
    last_excluded: unknown;
    /** Names a class that is neither a model nor a resource, so no file is ever published for it. */
    handle: unknown;
}

export interface FacilityRelations
{
    // Relations
    /** Published on demand: AuditTrail sits in no configured model directory. */
    audit_trails: AuditTrail[];
    /**
     * Names its targets in the docblock generic: AuditInspector sits in no configured model directory and is
     * published on demand, and the #[TsExclude]d model is left out of the union.
     */
    inspector: AuditInspector | User | null;
    // Counts
    audit_trails_count: number;
    inspector_count: number;
    // Exists
    audit_trails_exists: boolean;
    inspector_exists: boolean;
}

export interface FacilityAll extends Facility, FacilityMutators, FacilityRelations {}

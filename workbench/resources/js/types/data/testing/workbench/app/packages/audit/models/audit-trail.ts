import type { Facility } from '../../../models';
import type { AuditNote } from '.';

/**
 * Lives outside every configured model directory, as a package's model does, so it is published only because
 * Facility relates to it.
 *
 * @see Workbench\App\Packages\Audit\Models\AuditTrail
 */
export interface AuditTrail
{
    id: number;
    facility_id: number;
    action: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface AuditTrailRelations
{
    // Relations
    /** The facility the entry was recorded for. */
    facility: Facility;
    /** Published on demand in turn: reached only through this model. */
    notes: AuditNote[];
    // Counts
    facility_count: number;
    notes_count: number;
    // Exists
    facility_exists: boolean;
    notes_exists: boolean;
}

export interface AuditTrailAll extends AuditTrail, AuditTrailRelations {}

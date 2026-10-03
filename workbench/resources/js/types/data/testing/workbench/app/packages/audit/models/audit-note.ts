import type { AuditTrail } from '.';

/**
 * Reached only through AuditTrail, which is itself published on demand.
 *
 * @see Workbench\App\Packages\Audit\Models\AuditNote
 */
export interface AuditNote
{
    id: number;
    audit_trail_id: number;
    body: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface AuditNoteRelations
{
    // Relations
    /** The entry the note belongs to. */
    trail: AuditTrail;
    // Counts
    trail_count: number;
    // Exists
    trail_exists: boolean;
}

export interface AuditNoteAll extends AuditNote, AuditNoteRelations {}

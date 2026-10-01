/**
 * Named only by a morphTo docblock generic on Facility, so it is published on demand through that generic.
 *
 * @see Workbench\App\Packages\Audit\Models\AuditInspector
 */
export interface AuditInspector
{
    // Columns
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}

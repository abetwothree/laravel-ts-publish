/**
 * Set-only mutators whose docblock Get is `never`: it records no getter, not a read type.
 *
 * @see Workbench\App\Models\OutgoingNote
 */
export interface OutgoingNote
{
    id: number;
    subject: string;
    type: string;
    created_at: string | null;
    updated_at: string | null;
}

/**
 * A work shift, whose resource publishes each value as json_encode() writes it.
 *
 * @see Workbench\App\Models\Shift
 */
export interface Shift
{
    // Columns
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}

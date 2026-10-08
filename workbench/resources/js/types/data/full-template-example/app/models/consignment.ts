/**
 * A consignment whose casts publish what Laravel serializes: a `timestamp` cast is the Unix integer, while the
 * `created_at` and `updated_at` columns keep the date type.
 *
 * @see Workbench\App\Models\Consignment
 */
export interface Consignment
{
    // Columns
    id: number;
    scanned_at: number | null;
    declared_value: number;
    legs: string | null;
    created_at: string | null;
    updated_at: string | null;
}

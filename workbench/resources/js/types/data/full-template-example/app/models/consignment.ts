/**
 * A consignment whose casts publish what Laravel serializes: a `timestamp` cast is the Unix integer and a `decimal:2`
 * cast a string on every driver, while the `created_at` and `updated_at` columns keep the date type.
 *
 * @see Workbench\App\Models\Consignment
 */
export interface Consignment
{
    // Columns
    id: number;
    scanned_at: number | null;
    declared_value: string;
    legs: ({ code: string; sequence: number; stop: { note: string | null; name: string; lat: number; lng: number } | null })[];
    created_at: string | null;
    updated_at: string | null;
}

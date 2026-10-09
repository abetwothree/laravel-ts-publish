/**
 * Reads each cast through the model, so the resource publishes what the cast returns, as the model does.
 *
 * @see Workbench\App\Http\Resources\ConsignmentResource
 */
export interface ConsignmentResource
{
    id: number;
    scanned_at: number | null;
    declared_value: string;
    legs: ({ code: string; sequence: number; stop: { note: string | null; name: string; lat: number; lng: number } | null })[];
}

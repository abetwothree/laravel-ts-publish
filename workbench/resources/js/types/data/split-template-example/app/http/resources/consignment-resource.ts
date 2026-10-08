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
    legs: string | null;
}

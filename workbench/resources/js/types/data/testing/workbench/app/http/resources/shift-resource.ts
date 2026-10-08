/**
 * Publishes each value as json_encode() writes it, not as `__toString()` reads it.
 *
 * @see Workbench\App\Http\Resources\ShiftResource
 */
export interface ShiftResource
{
    fault: Record<string, never>;
    handover_note: string | null;
    checked_at: string | null;
}

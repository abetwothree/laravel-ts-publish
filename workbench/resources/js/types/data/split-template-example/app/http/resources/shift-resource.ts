/**
 * Publishes each value as json_encode() writes it, not as `__toString()` reads it.
 *
 * @see Workbench\App\Http\Resources\ShiftResource
 */
export interface ShiftResource
{
    last_fault: Record<string, never>;
    fault: Record<string, never>;
    started_at: { date: string; timezone_type: number; timezone: string };
    handover_note: string | null;
    next_bell: string;
    checked_at: string | null;
}

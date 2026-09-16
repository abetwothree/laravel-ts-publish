/**
 * Filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * @see Workbench\App\Http\Resources\ReleaseColumnsResource
 */
export interface ReleaseColumnsResource
{
    id: number;
    columns: { named: unknown; rest: unknown; picked: Record<string, unknown>; left: Record<string, unknown> };
    picks: { named: unknown; rest: unknown; picked: Record<string, unknown>; left: Record<string, unknown> };
}

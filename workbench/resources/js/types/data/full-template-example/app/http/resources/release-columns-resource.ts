import type { Release } from '../../models';

/**
 * Filters written inside the model itself: a method body the resource forwards to, and an accessor it reads.
 *
 * @see Workbench\App\Http\Resources\ReleaseColumnsResource
 */
export interface ReleaseColumnsResource
{
    id: number;
    columns: { named: { major: number; minor: number }; rest: { id: number; major: number; minor: number; created_at: string | null; updated_at: string | null }; picked: Record<string, unknown>; left: Record<string, unknown> };
    picks: { named: Pick<Release, 'major' | 'minor'>; rest: Pick<Release, 'id' | 'major' | 'minor' | 'created_at' | 'updated_at'>; picked: Record<string, unknown>; left: Record<string, unknown> };
}

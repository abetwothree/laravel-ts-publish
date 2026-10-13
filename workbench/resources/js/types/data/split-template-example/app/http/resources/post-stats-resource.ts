/**
 * A resource that carries a value next to its model through a promoted constructor property.
 *
 * @see Workbench\App\Http\Resources\PostStatsResource
 */
export interface PostStatsResource
{
    id: number;
    title: number;
    stats: { views: number; shares: number } | null;
    views: number | null;
    share_count: number | null;
    blockers: { message: string; route?: string }[];
    checks_count: string;
    loaded_checks_count?: string;
    loaded_checks_label?: string;
}

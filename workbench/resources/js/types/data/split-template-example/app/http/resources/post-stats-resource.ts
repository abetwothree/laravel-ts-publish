/**
 * A resource that carries a value next to its model through a promoted constructor property.
 *
 * @see Workbench\App\Http\Resources\PostStatsResource
 */
export interface PostStatsResource
{
    id: number;
    stats: { views: number; shares: number } | null;
    views: number | null;
    share_count: number | null;
}

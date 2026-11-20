/**
 * The placement an unpinned post sends: one arm of PinnedPostResource's discriminated union (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\FeedPlacementResource
 */
export interface FeedPlacementResource
{
    published_at: string | null;
    rating: string | null;
}

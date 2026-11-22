/**
 * A held local that keeps the `cache()` list a spread may write over an earlier string (GH-162 E1 guard).
 *
 * @see Workbench\App\Http\Resources\DroppedArms\FoldSiteDropResource
 */
export interface FoldSiteDropResource
{
    spread_local_held: { k: string | number[] };
}

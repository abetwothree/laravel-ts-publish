/**
 * Fixture: a model-less resource whose `toArray()` calls and spreads helpers NoticeResource overrides, and spreads
 * the `with()` it leaves to JsonResource.
 *
 * @see Workbench\App\Http\Resources\BaseNoticeResource
 */
export interface BaseNoticeResource
{
    headline: string | number;
    base_only: number;
}

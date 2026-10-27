/**
 * Fixture: a model-less resource whose `toArray()` calls and spreads helpers NoticeResource overrides, spreads the
 * `with()` it leaves to JsonResource, and reads a constant and an untyped property NoticeResource redeclares.
 *
 * @see Workbench\App\Http\Resources\BaseNoticeResource
 */
export interface BaseNoticeResource
{
    headline: string | number;
    label: string;
    tier: string;
    base_only: number;
}

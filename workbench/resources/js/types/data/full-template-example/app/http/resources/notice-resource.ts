/**
 * Fixture: the runtime class of BaseNoticeResource's `toArray()`, overriding the helper it calls, the helper it
 * spreads and the `with()` it spreads.
 *
 * @see Workbench\App\Http\Resources\NoticeResource
 */
export interface NoticeResource
{
    headline: string | number;
    base_only: number;
}

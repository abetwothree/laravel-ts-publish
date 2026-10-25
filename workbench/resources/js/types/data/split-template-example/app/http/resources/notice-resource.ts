/**
 * Fixture: the runtime class of BaseNoticeResource's `toArray()`, overriding the helper it calls, the helper it
 * spreads, the `with()` it spreads, and the constant and property it reads.
 *
 * @see Workbench\App\Http\Resources\NoticeResource
 */
export interface NoticeResource
{
    headline: string;
    label: number;
    tier: number;
    child_only: boolean;
    meta: number;
}

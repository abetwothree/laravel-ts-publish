/**
 * Fixture: a runtime class of BaseNoticeResource's `toArray()` that writes the untyped `$tier` it inherits through a
 * typed setter.
 *
 * @see Workbench\App\Http\Resources\TieredNoticeResource
 */
export interface TieredNoticeResource
{
    headline: string | number;
    label: string;
    tier: string;
    base_only: number;
}

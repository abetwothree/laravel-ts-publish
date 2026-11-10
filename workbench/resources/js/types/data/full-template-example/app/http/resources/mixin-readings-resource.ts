/**
 * A resource declared through `@mixin` to hold a reading sheet, whose docblock list has a `mixed` member the
 * `toArray()` `@return` docblock types (GH-154).
 *
 * @see Workbench\App\Http\Resources\MixinReadingsResource
 */
export interface MixinReadingsResource
{
    entries: { label: string | null }[];
}

/**
 * Reads a model's undocumented accessor that maps a cast array, under a `@return` typing it (GH-154).
 *
 * @see Workbench\App\Http\Resources\TaggedImageResource
 */
export interface TaggedImageResource
{
    tag_lists: null[];
    url: string;
}

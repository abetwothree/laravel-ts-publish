/**
 * Tags each row with a value no rule types or a string; BoundaryReadingsResource spreads it as its parent (GH-154).
 *
 * @see Workbench\App\Http\Resources\RowTagsResource
 */
export interface RowTagsResource
{
    tags: string[];
    id: number;
}

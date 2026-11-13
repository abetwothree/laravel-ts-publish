/**
 * Spreads a custom parent's `toArray()` that drops an arm beside a string, under a `@return` typing both (GH-154).
 *
 * @see Workbench\App\Http\Resources\BoundaryReadingsResource
 */
export interface BoundaryReadingsResource
{
    tags: (string | number[])[];
    id: number;
    extra: boolean;
}

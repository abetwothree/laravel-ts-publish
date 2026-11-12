/**
 * Undocumented helpers that drop an arm, one spread and one read as a value, under a `@return` typing them (GH-154).
 *
 * @see Workbench\App\Http\Resources\HelperReadingsResource
 */
export interface HelperReadingsResource
{
    rows: { name: string }[];
    sections: { items: { t: number[] }[] };
}

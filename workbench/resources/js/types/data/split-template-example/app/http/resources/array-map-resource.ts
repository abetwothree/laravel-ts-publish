/**
 * Exercises array_map(): one array keeps its keys, two arrays re-index into a list, and a callable of any form is
 * called with each element.
 *
 * @see Workbench\App\Http\Resources\ArrayMapResource
 */
export interface ArrayMapResource
{
    messages: string[];
    doubled: { low: number; high: number };
    zipped: [string | null, number | null][];
    names: string[];
}

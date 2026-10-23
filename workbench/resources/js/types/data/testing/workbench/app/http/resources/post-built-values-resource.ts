/**
 * Exercises: a value-position closure, and a helper read as a value, that build an array in a variable through key
 * writes and return it. Each reads the variable through its writes, as a method's own returned variable is read, and
 * a helper's literal return stands beside it as one more arm.
 *
 * @see Workbench\App\Http\Resources\PostBuiltValuesResource
 */
export interface PostBuiltValuesResource
{
    built?: { id: number; title: string };
    branched?: { id: number; flag: string | number };
    key_written: { id: number } | { id: number; title: string };
    unset_beside: { id: number };
}

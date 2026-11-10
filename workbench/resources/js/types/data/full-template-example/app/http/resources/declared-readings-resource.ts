/**
 * Reads a sheet whose members the body types less than the `toArray()` `@return` docblock does (GH-154): a docblock
 * list with a `mixed` member, a map over an array with no element type, and array_filter() of a literal holding a
 * value with no type.
 *
 * @see Workbench\App\Http\Resources\DeclaredReadingsResource
 */
export interface DeclaredReadingsResource
{
    entries: { label: unknown }[];
    labels: { label: unknown }[];
    flags: { note?: unknown; weight?: number };
}

/**
 * Exercises local variable type tracking inside toArray().
 *
 * `shadowed` is a Task 12 regression: a variable assigned at the top level AND
 * reassigned inside nested control flow must not resolve through the (possibly
 * stale) top-level binding alone — it joins the values that can reach the read.
 *
 * @see Workbench\App\Http\Resources\LocalVarResource
 */
export interface LocalVarResource
{
    label: string;
    key: number;
    shadowed: string | null;
}

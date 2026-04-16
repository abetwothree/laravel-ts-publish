/**
 * Resource using a positive instanceof guard (not negated). Also includes inline arrays with optional keys and an empty inline array to exercise additional coverage paths.
 *
 * @see Workbench\App\Http\Resources\MediaTypePositiveInstanceOfResource
 */
export interface MediaTypePositiveInstanceOfResource
{
    name: string;
    value: string;
    meta: { label?: unknown };
    empty: Record<string, unknown>;
}

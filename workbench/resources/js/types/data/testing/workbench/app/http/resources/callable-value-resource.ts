import type { UserResource } from '.';

/**
 * First-class callables as values. A key that holds one sends the `{}` json_encode() writes for a Closure, while a
 * when() or whenLoaded() value that holds one is called first, so the key sends that call's return.
 *
 * @see Workbench\App\Http\Resources\CallableValueResource
 */
export interface CallableValueResource
{
    length: Record<string, never>;
    upper: Record<string, never>;
    key: Record<string, never>;
    label: Record<string, never>;
    supervisor_resource: Record<string, never>;
    nested: { length: Record<string, never> };
    when_key?: number;
    when_label?: string;
    label_or_zero: number | string;
    supervisor?: UserResource | null;
}

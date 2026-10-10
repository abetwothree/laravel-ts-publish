import type { Post, Product } from '../../models';

/**
 * Interpolated keys written in the returned array and inside merges beside same-pattern keys, so each signature's
 * value covers every key it matches, and merges of a helper's keys and of the model itself.
 *
 * @see Workbench\App\Http\Resources\MergedSignatureKeysResource
 */
export interface MergedSignatureKeysResource
{
    id: number;
    [key: `${string}_note`]: string | number | undefined;
    main_label: string;
    [key: `${string}_label`]: number | string | undefined;
    name?: string;
    slug?: string;
    color?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    posts?: Post[];
    products?: Product[];
}

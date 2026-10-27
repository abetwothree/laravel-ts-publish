import type { MediaTypeKind, MediaTypeType } from '../../enums';

/**
 * Resource using a positive instanceof guard (not negated).
 * Also includes inline arrays with optional keys and an empty inline array
 * to exercise additional coverage paths.
 *
 * @see Workbench\App\Http\Resources\MediaTypePositiveInstanceOfResource
 */
export interface MediaTypePositiveInstanceOfResource
{
    name?: MediaTypeKind;
    value?: MediaTypeType;
    meta?: { label?: string };
    empty?: never[];
}

import type { User as CrmUser } from '../../../crm/models';
import type { Post, Product, User as WorkbenchUser } from '../../models';

/**
 * A whenLoaded() closure whose variadic parameter collects a morphTo. The list holds whichever target loaded, never
 * null: Laravel calls the closure only for a loaded value that is not null. `reviewable` can load as null, so its key
 * also takes the `null` whenLoaded() returns; `imageable` cannot. The reviewable targets share a basename, so each
 * keeps its alias.
 *
 * @see Workbench\App\Http\Resources\ImageSubjectsResource
 */
export interface ImageSubjectsResource
{
    subjects?: (CrmUser | WorkbenchUser)[] | null;
    owners?: (Post | Product | WorkbenchUser | CrmUser)[];
}

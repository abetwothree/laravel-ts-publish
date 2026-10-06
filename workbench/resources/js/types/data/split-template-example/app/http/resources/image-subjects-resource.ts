import type { User as CrmUser } from '../../../crm/models';
import type { Post, Product, User as ModelsUser } from '../../models';

/**
 * A whenLoaded() closure whose variadic parameter collects a morphTo. The list holds whichever target loaded, never
 * null: Laravel calls the closure only for a loaded value. `reviewable` can load as null, so its key also takes the
 * `null` whenLoaded() returns; `imageable` cannot. The reviewable targets share a basename, so each keeps its alias.
 *
 * @see Workbench\App\Http\Resources\ImageSubjectsResource
 */
export interface ImageSubjectsResource
{
    subjects?: (CrmUser | ModelsUser)[] | null;
    owners?: (Post | Product | ModelsUser | CrmUser)[];
}

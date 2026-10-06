import type { Category } from '../../models';
import type { CategoryResource } from '.';

/**
 * Every whenLoaded() spelling over `parent`, a BelongsTo whose nullable foreign key makes it load as null, and over
 * `children`, a HasMany that loads as a collection, then every resource built around `parent`. Laravel returns null for
 * a relation loaded as null before it reads the value, and serializes a resource wrapping null as null, so each
 * `parent` key publishes `| null`; a `children` key never does.
 *
 * @see Workbench\App\Http\Resources\CategoryLineageResource
 */
export interface CategoryLineageResource
{
    id: number;
    parent_name?: string | null;
    parent_list?: Category[] | null;
    parent_label?: string | null;
    parent_or_absent: Category | string | null;
    parent_named_default: Category | string | null;
    parent_name_or_absent: string | null;
    parent_callable?: CategoryResource | null;
    parent_new_loaded?: CategoryResource | null;
    parent_make_direct: CategoryResource | null;
    parent_nullsafe_resource: CategoryResource | null;
    parent_in_array: { parent: CategoryResource | null };
    parent_when?: CategoryResource | null;
    children_names?: string[];
    children_list?: Category[][];
}

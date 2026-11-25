/**
 * `self` inside a generic `@var` in a final class's trait and own body, beside the class named there (GH-134; plan
 * task T3).
 *
 * @see Workbench\App\Http\Resources\DocblockReads\SelfGenericVarResource
 */
export interface SelfGenericVarResource
{
    self_kid_names: string[];
    named_kid_names: string[];
    self_first_kid_name: string | null;
    named_first_kid_name: string | null;
    self_kid_name_list: string[];
    named_kid_name_list: string[];
    self_kid_sort_orders: number[];
    named_kid_sort_orders: number[];
    own_self_kid_name_list: string[];
    own_named_kid_name_list: string[];
    own_self_first_kid_name: string | null;
    own_named_first_kid_name: string | null;
    own_self_kid_sort_orders: number[];
    own_named_kid_sort_orders: number[];
}

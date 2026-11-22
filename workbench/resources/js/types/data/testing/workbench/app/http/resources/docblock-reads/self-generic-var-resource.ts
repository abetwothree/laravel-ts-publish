/**
 * `self` inside a generic `@var` in a final class's trait, beside the class named there (GH-134; plan task T3).
 *
 * @see Workbench\App\Http\Resources\DocblockReads\SelfGenericVarResource
 */
export interface SelfGenericVarResource
{
    self_kid_names: unknown;
    named_kid_names: string[];
    self_first_kid_name: unknown;
    named_first_kid_name: string | null;
}

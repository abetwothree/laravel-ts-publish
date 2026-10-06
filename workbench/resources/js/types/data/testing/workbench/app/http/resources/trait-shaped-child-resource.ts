/**
 * Inherits a toArray() its parent takes from a trait, so the analysis walks to the parent and reads the trait there.
 *
 * @see Workbench\App\Http\Resources\TraitShapedChildResource
 */
export interface TraitShapedChildResource
{
    id: number;
    name: string;
    shaped_by: string;
}

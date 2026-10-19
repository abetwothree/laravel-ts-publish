/**
 * Takes its toArray() from a trait declared in another file, which is still the resource's own method.
 *
 * @see Workbench\App\Http\Resources\TraitShapedResource
 */
export interface TraitShapedResource
{
    id: number;
    name: string;
    shaped_by: string;
}

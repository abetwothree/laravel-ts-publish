/**
 * An arm whose members no reader lists: named keys beside a template-literal signature (GH-161 D3).
 *
 * @see Workbench\App\Http\Resources\Spreads\TagSignatureArmResource
 */
export interface TagSignatureArmResource
{
    id: number;
    name: string;
    [key: `${string}_tag`]: string | undefined;
}

/**
 * An arm ArmBesideMapResource spreads beside a string map, whose `by` the map's value does not fit (GH-161 D3).
 *
 * @see Workbench\App\Http\Resources\Spreads\TitleFlagArmResource
 */
export interface TitleFlagArmResource
{
    name: string;
    by: boolean;
}

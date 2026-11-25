/**
 * An arm ArmBesideMapResource spreads beside two maps, whose `note` is always null (GH-161 D3).
 *
 * @see Workbench\App\Http\Resources\Spreads\NullNoteArmResource
 */
export interface NullNoteArmResource
{
    id: number;
    note: null;
}

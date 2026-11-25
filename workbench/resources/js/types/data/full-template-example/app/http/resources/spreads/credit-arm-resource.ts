/**
 * An arm BaseFormsSpreadResource spreads inside a merge, over an earlier key of its own name (GH-121 B6).
 *
 * @see Workbench\App\Http\Resources\Spreads\CreditArmResource
 */
export interface CreditArmResource
{
    credited_title: string;
    credited_flag: boolean;
}

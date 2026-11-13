/**
 * Joins where one branch writes a value no rule types, under a `@return` member typing both branches (GH-154).
 *
 * @see Workbench\App\Http\Resources\JoinReadingsResource
 */
export interface JoinReadingsResource
{
    params: { t: string | number[] }[];
    walks: { k: string | number[] }[];
    spreads: { k: string | number[]; id: number }[];
    relays: { k?: string | number[]; id: number }[];
    receivers: { name: string | number }[];
    named: { name: string | number }[];
}

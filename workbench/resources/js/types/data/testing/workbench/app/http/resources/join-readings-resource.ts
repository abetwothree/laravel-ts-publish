/**
 * Joins where one branch writes a value no rule types, under a `@return` member typing both branches (GH-154).
 *
 * @see Workbench\App\Http\Resources\JoinReadingsResource
 */
export interface JoinReadingsResource
{
    params: { t: string }[];
    walks: { k: string }[];
    spreads: { k: string; id: unknown }[];
    relays: { k?: string; id: unknown }[];
    receivers: { name: unknown }[];
    named: { name: unknown }[];
}

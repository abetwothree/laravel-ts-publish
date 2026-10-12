/**
 * A spread helper called inside closures whose parameter is named like a local the helper writes itself.
 *
 * @see Workbench\App\Http\Resources\SpreadHelperScopeResource
 */
export interface SpreadHelperScopeResource
{
    has_t?: { v: number; k: number };
    transform_t?: { v: number };
    loaded_t?: { v: number };
    v: number;
}

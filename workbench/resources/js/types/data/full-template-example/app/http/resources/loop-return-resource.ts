/**
 * Exercises collectDirectReturns loop branch in toArray(). `$item` is bound to the `items`
 * relation's element model (OrderItem), so `$item->name` resolves instead of degrading to unknown.
 *
 * @see Workbench\App\Http\Resources\LoopReturnResource
 */
export interface LoopReturnResource
{
    id: number;
    first_item_name?: string;
    total?: string;
}

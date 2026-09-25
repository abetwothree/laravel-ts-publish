/**
 * A resource over a value object rather than a model. The inline `@var` on each local names what it holds, for the
 * reads after its assignment and before the variable is written again.
 *
 * @see Workbench\App\Http\Resources\CartTotalsResource
 */
export interface CartTotalsResource
{
    subtotal: number;
    chargeable: boolean;
    count: number;
    note: string | null;
    totals: { subtotal: number; chargeable: boolean; count: number; hasExtras: boolean };
    unnamed_count: number;
    label: string;
    count_before: number;
    count_after: unknown;
    missing_count: unknown;
}

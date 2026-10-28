import type { ChartType } from '../packages/ui';

/**
 * A board whose appended accessor is a value of a composed class, so its interface imports the class's module.
 *
 * @see Workbench\App\Models\UiBoard
 */
export interface UiBoard
{
    // Columns
    id: number;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** A chart, by a getter closure that declares its class. */
    chart: ChartType;
}

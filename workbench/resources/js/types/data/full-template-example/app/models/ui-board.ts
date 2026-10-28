import type { UiGauge, UiNode } from '@js/types/ui';
import type { ChartType } from '../packages/ui';
import type { User } from '.';

/**
 * A board whose appended accessor is a value of a composed class, so its interface imports the class's module.
 *
 * @see Workbench\App\Models\UiBoard
 */
export interface UiBoard
{
    // Columns
    id: number;
    panels: UiNode[] | null;
    charts: ChartType[] | null;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    /** An uploader, typed only by this docblock. */
    uploader_from_docblock: User | null;
    /** A gauge, typed only by this docblock. */
    gauge_from_docblock: UiGauge;
    /** A gauge, by a getter closure that declares its class. */
    gauge_attr: UiGauge;
    /** A chart, by a getter closure that declares its class. */
    chart: ChartType;
}

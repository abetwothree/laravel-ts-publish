import type { ChartType, DialType } from '../../packages/ui';

/**
 * Fixture: values of composed classes through declared helpers, so the file imports their `…Type` from their module.
 *
 * @see Workbench\App\Http\Resources\UiPanelResource
 */
export interface UiPanelResource
{
    chart: ChartType;
    dial: DialType | null;
}

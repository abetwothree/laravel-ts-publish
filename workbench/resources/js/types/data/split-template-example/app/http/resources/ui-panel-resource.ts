import type { UiNode } from '@js/types/ui';
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
    panel: UiNode;
    panels: UiNode[];
    when_panel?: UiNode;
    maybe_chart: ChartType | null;
    plain: unknown;
}

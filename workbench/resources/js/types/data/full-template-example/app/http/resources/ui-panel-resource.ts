import type { UiNode } from '@js/types/ui';
import type { ChartType, DialType } from '../../packages/ui';

/**
 * Fixture: values of overridden classes, through declared helpers and through `new`, so the file imports each override
 * and each composed class's `…Type` from its module.
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
}

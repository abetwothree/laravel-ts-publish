import type { UiAmount, UiGauge, UiIcon, UiNode } from '@js/types/ui';
import type { ChartType, DialType } from '../packages/ui';

/** @see Workbench\App\Events\UiBoardUpdated */
export interface UiBoardUpdated {
    panel: UiNode;
    gauge: UiGauge;
    pennies: UiAmount;
    badge: UiIcon;
    maybe: UiNode | null;
    chart: ChartType;
    dial: DialType;
    maybeChart: ChartType | null;
    charts: ChartType[];
    board: { panel: UiNode; chart: ChartType; gauge: UiGauge | null; total: UiAmount; charts: ChartType[] };
}

import type { UiAmount, UiGauge, UiIcon, UiNode } from '@js/types/ui';

/** @see Workbench\App\Events\UiBoardUpdated */
export interface UiBoardUpdated {
    panel: UiNode;
    gauge: UiGauge;
    pennies: UiAmount;
    badge: UiIcon;
    maybe: UiNode | null;
}

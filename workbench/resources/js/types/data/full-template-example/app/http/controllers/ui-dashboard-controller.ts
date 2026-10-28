import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { UiAmount, UiGauge, UiNode } from '@js/types/ui';
import type { ChartType } from '../../packages/ui';

export type IndexPageProps = Inertia.SharedData & { chart: UiNode, widgets: UiNode[], maybe: UiNode | null, coll: UiNode[], composed: ChartType, gauge: UiGauge, pennies: UiAmount, deferred?: UiGauge, optional?: ChartType, shaped: { chart: ChartType; panel: UiNode; gauges: UiGauge[] }, untyped: { panel: UiNode; chart: ChartType } };

export const index = annotatePageProps<IndexPageProps>()(defineRoute({
    name: 'ui.dashboard',
    url: '/ui/dashboard',
    methods: ['get', 'head'] as const,
    component: 'Ui/Dashboard',
}));

/**
 * Renders props that are values of a class with one inherited override and of a composed class.
 *
 * @see Workbench\App\Http\Controllers\UiDashboardController
 */
const UiDashboardController = {
    index,
};

export default UiDashboardController;

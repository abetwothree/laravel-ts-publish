import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { Facility } from '../../models';
import type { AuditTrail } from '../../packages/audit/models';

export type ShowPageProps = Inertia.SharedData & { facility: Facility, trail: AuditTrail | null, record: unknown };

/** Passes a model published on demand and a model that is never published. */
export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'facilities.show',
    url: '/facilities/{facility}',
    methods: ['get', 'head'] as const,
    args: [{name: 'facility', required: true, _routeKey: 'id'}] as const,
    component: 'Facility/Show',
}));

/** @see Workbench\App\Http\Controllers\InertiaFacilityController */
const InertiaFacilityController = {
    show,
};

export default InertiaFacilityController;

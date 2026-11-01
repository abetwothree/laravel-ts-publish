import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { StatusKind as CrmStatusKind, StatusType as CrmStatusType } from '../../../crm/enums';
import type { StatusKind as WorkbenchStatusKind, StatusType as WorkbenchStatusType } from '../../enums';

export type PairPageProps = Inertia.SharedData & { app: WorkbenchStatusType, crm: CrmStatusType, app_cast: WorkbenchStatusType, crm_cast: CrmStatusType };

export const pair = annotatePageProps<PairPageProps>()(defineRoute({
    name: 'deals.same-named-enums',
    url: '/deals/{deal}/same-named-enums',
    methods: ['get', 'head'] as const,
    args: [{name: 'deal', required: true, _routeKey: 'id'}] as const,
    component: 'SameNamedEnums/Pair',
}));

export type NamesPageProps = Inertia.SharedData & { app_name: WorkbenchStatusKind, crm_name: CrmStatusKind };

export const names = annotatePageProps<NamesPageProps>()(defineRoute({
    name: 'deals.same-named-enum-names',
    url: '/deals/{deal}/same-named-enum-names',
    methods: ['get', 'head'] as const,
    args: [{name: 'deal', required: true, _routeKey: 'id'}] as const,
    component: 'SameNamedEnums/Names',
}));

/**
 * Renders two same-named enums as cases, as a deal's casts and by their case names, so its route file aliases both.
 *
 * @see Workbench\App\Http\Controllers\InertiaSameNamedEnumsController
 */
const InertiaSameNamedEnumsController = {
    pair,
    names,
};

export default InertiaSameNamedEnumsController;

import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { User } from '../../models';

export type ShowPageProps = Inertia.SharedData & { stamp: string, label: string, viewer: User | null, closure_label: string };

/** Passes first-class callables as props, which Inertia calls before it sends them, like a closure prop. */
export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'facilities.callables',
    url: '/facilities/{facility}/callables',
    methods: ['get', 'head'] as const,
    args: [{name: 'facility', required: true, _routeKey: 'id'}] as const,
    component: 'Facility/Callables',
}));

/** @see Workbench\App\Http\Controllers\InertiaCallablePropsController */
const InertiaCallablePropsController = {
    show,
};

export default InertiaCallablePropsController;

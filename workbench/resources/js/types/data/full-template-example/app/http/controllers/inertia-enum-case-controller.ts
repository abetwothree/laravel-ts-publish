import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { StatusType } from '../../enums';

export type ShowPageProps = Inertia.SharedData & { status: StatusType, kept: StatusType, nested: { status: StatusType }, cases: StatusType[], reading: { date: string; timezone_type: number; timezone: string } };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'posts.enum-case',
    url: '/posts/{post}/enum-case',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/EnumCase',
}));

/**
 * Renders bare enum cases and a `new` value as page props. One `Status` only: two same-named enums in one route file
 * import one name twice.
 *
 * @see Workbench\App\Http\Controllers\InertiaEnumCaseController
 */
const InertiaEnumCaseController = {
    show,
};

export default InertiaEnumCaseController;

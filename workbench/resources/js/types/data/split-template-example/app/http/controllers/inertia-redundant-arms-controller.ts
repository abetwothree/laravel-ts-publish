import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { PriorityType, StatusType } from '../../enums';

export type ShowPageProps = Inertia.SharedData & { priorities: never[] | (PriorityType | null)[], status: 0 | StatusType, status_or_draft?: 0 | StatusType };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'posts.redundant-arms',
    url: '/posts/{post}/redundant-arms',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/RedundantArms',
}));

/**
 * Two renders of one page: an empty list and a case value in one, the list and the enum in the other (GH-166).
 *
 * @see Workbench\App\Http\Controllers\InertiaRedundantArmsController
 */
const InertiaRedundantArmsController = {
    show,
};

export default InertiaRedundantArmsController;

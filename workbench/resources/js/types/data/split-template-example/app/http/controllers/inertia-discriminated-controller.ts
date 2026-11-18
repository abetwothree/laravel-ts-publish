import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { PriorityType } from '../../enums';

export type ShowPageProps = Inertia.SharedData & { pinned: boolean, pinned_title: string | null, placement: { pinned: boolean; at: string | null; rank: PriorityType | null } };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'posts.discriminated',
    url: '/posts/{post}/discriminated',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/Discriminated',
}));

/**
 * Page props that follow a sibling prop's value (GH-157): a top-level pair, which stays two plain props, and the same
 * pair inside a nested prop.
 *
 * @see Workbench\App\Http\Controllers\InertiaDiscriminatedController
 */
const InertiaDiscriminatedController = {
    show,
};

export default InertiaDiscriminatedController;

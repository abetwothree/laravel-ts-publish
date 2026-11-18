import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { PriorityType } from '../../enums';
import type { PostResource } from '../resources';

export type ShowPageProps = Inertia.SharedData & { pinned: boolean, pinned_title: string | null, placement: { pinned: true; at: string | null; rank: PriorityType | null } | { pinned: false; at: string | null; rank: null } };

export const show = /* @__PURE__ */ annotatePageProps<ShowPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'posts.discriminated',
    url: '/posts/{post}/discriminated',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/Discriminated',
}));

export type ReadBackPageProps = Inertia.SharedData & { post: { data: PostResource; pinned: boolean; v: string | null }, placement: ({ pinned: true; at: string | null } | { pinned: false; at: null }) & { extra: number }, entered: Omit<({ pinned: true; at: string | null } | { pinned: false; at: null }), 'at'> & { at: string } };

/**
 * Props read back as keys: a resource's `additional()` keys that follow a sibling key, and dotted keys written
 * into a prop whose keys do, one beside its keys and one over a key it holds.
 */
export const readBack = /* @__PURE__ */ annotatePageProps<ReadBackPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'posts.discriminated.read-back',
    url: '/posts/{post}/discriminated/read-back',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/DiscriminatedReadBack',
}));

/**
 * Page props that follow a sibling prop's value (GH-157): a top-level pair, which stays two plain props, and the same
 * pair inside a nested prop.
 *
 * @see Workbench\App\Http\Controllers\InertiaDiscriminatedController
 */
const InertiaDiscriminatedController = {
    show,
    readBack,
};

export default InertiaDiscriminatedController;

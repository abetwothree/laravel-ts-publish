import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { Comment, Post, User } from '../../models';

export type IndexPageProps = Inertia.SharedData & { users: User[], posts: Post[], page: number };

/** Model collections plus a typed request read. */
export const index = annotatePageProps<IndexPageProps>()(defineRoute({
    name: 'user-shapes.index',
    url: '/user-shapes',
    methods: ['get', 'head'] as const,
    component: 'UserShapes/Index',
}));

export type ShowPageProps = Inertia.SharedData & { post: Post, draft: Post | null };

/** A found model and a nullable found model. */
export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'user-shapes.show',
    url: '/user-shapes/show/{id}',
    methods: ['get', 'head'] as const,
    args: [{name: 'id', required: true}] as const,
    component: 'UserShapes/Show',
}));

export type DeferredPageProps = Inertia.SharedData & { comments?: Comment[], tally?: number };

/** Inertia v2 partial-reload prop wrappers. */
export const deferred = annotatePageProps<DeferredPageProps>()(defineRoute({
    name: 'user-shapes.deferred',
    url: '/user-shapes/deferred',
    methods: ['get', 'head'] as const,
    component: 'UserShapes/Deferred',
}));

export type CompactedPageProps = Inertia.SharedData & { post: Post, comments: Comment[] };

/** Props supplied by compact() rather than an array literal. */
export const compacted = annotatePageProps<CompactedPageProps>()(defineRoute({
    name: 'user-shapes.compacted',
    url: '/user-shapes/compacted/{id}',
    methods: ['get', 'head'] as const,
    args: [{name: 'id', required: true}] as const,
    component: 'UserShapes/Compacted',
}));

export type ToggledPageProps = Inertia.SharedData & { post: Post | null, views?: number };

/** A props array assigned from a ternary, so the two branches disagree on one key. */
export const toggled = annotatePageProps<ToggledPageProps>()(defineRoute({
    name: 'user-shapes.toggled',
    url: '/user-shapes/toggled',
    methods: ['get', 'head'] as const,
    component: 'UserShapes/Toggled',
}));

export type ProfilePageProps = Inertia.SharedData & { user: User | null, stats: { views: number; likes: number } };

/** The authenticated user and a service method with an array-shape docblock. */
export const profile = annotatePageProps<ProfilePageProps>()(defineRoute({
    name: 'user-shapes.profile',
    url: '/user-shapes/profile',
    methods: ['get', 'head'] as const,
    component: 'UserShapes/Profile',
}));

export type MergedPageProps = Inertia.SharedData & { title: string, extra: boolean };

/** Props built with array_merge() over a local base array. */
export const merged = annotatePageProps<MergedPageProps>()(defineRoute({
    name: 'user-shapes.merged',
    url: '/user-shapes/merged',
    methods: ['get', 'head'] as const,
    component: 'UserShapes/Merged',
}));

export type BranchedPageProps = Inertia.SharedData & { post: Post | null, detail?: string };

/** Two renders of one component where `detail` exists on only one branch. */
export const branched = annotatePageProps<BranchedPageProps>()(defineRoute({
    name: 'user-shapes.branched',
    url: '/user-shapes/branched',
    methods: ['get', 'head'] as const,
    component: 'UserShapes/Branched',
}));

export type BoundPageProps = Inertia.SharedData & { post: Post, stats: { views: number; likes: number } };

/** A route-bound model parameter. */
export const bound = annotatePageProps<BoundPageProps>()(defineRoute({
    name: 'user-shapes.bound',
    url: '/user-shapes/bound/{post}',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'UserShapes/Bound',
}));

/**
 * The Inertia shapes real applications write that the rest of the workbench never exercised:
 * Eloquent finders, Inertia v2 prop wrappers, compact(), a ternary-assigned props array,
 * array_merge() props, a request-typed prop, a service call, and a two-branch render.
 *
 * @see Workbench\App\Http\Controllers\InertiaUserShapesController
 */
const InertiaUserShapesController = {
    index,
    show,
    deferred,
    compacted,
    toggled,
    profile,
    merged,
    branched,
    bound,
};

export default InertiaUserShapesController;

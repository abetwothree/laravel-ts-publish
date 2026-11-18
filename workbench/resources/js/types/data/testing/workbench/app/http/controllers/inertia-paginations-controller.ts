import { defineRoute, annotatePageProps } from '@tolki/ts';

import type { CursorPaginator, LengthAwarePaginator, SimplePaginator } from '@tolki/types';
import type { Post } from '../../models';

export type LengthAwarePageProps = Inertia.SharedData & { posts: LengthAwarePaginator<Post> };

/** Test page type is { posts: LengthAwarePaginator<Post> } */
export const lengthAware = /* @__PURE__ */ annotatePageProps<LengthAwarePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'pagination.length-aware',
    url: '/pagination/length-aware',
    methods: ['get', 'head'] as const,
    component: 'Collections/Index',
}));

export type SimplePageProps = Inertia.SharedData & { posts: SimplePaginator<Post> };

/** Test page type is { posts: SimplePaginator<Post> } */
export const simple = /* @__PURE__ */ annotatePageProps<SimplePageProps>()(/* @__PURE__ */ defineRoute({
    name: 'pagination.simple',
    url: '/pagination/simple',
    methods: ['get', 'head'] as const,
    component: 'Collections/Simple',
}));

export type CursorPageProps = Inertia.SharedData & { posts: CursorPaginator<Post> };

/** Test page type is { posts: CursorPaginator<Post> } */
export const cursor = /* @__PURE__ */ annotatePageProps<CursorPageProps>()(/* @__PURE__ */ defineRoute({
    name: 'pagination.cursor',
    url: '/pagination/cursor',
    methods: ['get', 'head'] as const,
    component: 'Collections/Cursor',
}));

/** @see Workbench\App\Http\Controllers\InertiaPaginationsController */
const InertiaPaginationsController = {
    lengthAware,
    simple,
    cursor,
};

export default InertiaPaginationsController;

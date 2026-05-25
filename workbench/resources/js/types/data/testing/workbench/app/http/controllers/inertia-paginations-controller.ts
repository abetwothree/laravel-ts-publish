import { defineRoute, annotatePageProps } from '@tolki/ts';
import type { CursorPaginator, LengthAwarePaginator, SimplePaginator } from '@tolki/types';
import type { Post } from '../../models';

export type LengthAwarePageProps = Inertia.SharedData & { posts: LengthAwarePaginator<Post> };

/**
  * Test page type is { posts: LengthAwarePaginator<Post> }
  */
export const lengthAware = annotatePageProps<LengthAwarePageProps>()(defineRoute({
    name: 'pagination.length-aware',
    url: '/pagination/length-aware',
    methods: ['get'] as const,
    component: 'Collections/Index',
}));

export type SimplePageProps = Inertia.SharedData & { posts: SimplePaginator<Post> };

/**
  * Test page type is { posts: SimplePaginator<Post> }
  */
export const simple = annotatePageProps<SimplePageProps>()(defineRoute({
    name: 'pagination.simple',
    url: '/pagination/simple',
    methods: ['get'] as const,
    component: 'Collections/Simple',
}));

export type CursorPageProps = Inertia.SharedData & { posts: CursorPaginator<Post> };

/**
  * Test page type is { posts: CursorPaginator<Post> }
  */
export const cursor = annotatePageProps<CursorPageProps>()(defineRoute({
    name: 'pagination.cursor',
    url: '/pagination/cursor',
    methods: ['get'] as const,
    component: 'Collections/Cursor',
}));

/**
 * @see Workbench\App\Http\Controllers\InertiaPaginationsController
 */
const InertiaPaginationsController = {
    lengthAware,
    simple,
    cursor,
};

export default InertiaPaginationsController;

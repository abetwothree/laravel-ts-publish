import { defineRoute, annotatePageProps } from '@tolki/ts';
import type { LengthAwarePaginator } from '@tolki/types';
import type { Post } from '../../models';

export type IndexPageProps = Inertia.SharedData & { posts: LengthAwarePaginator<Post> };

export const index = annotatePageProps<IndexPageProps>()(defineRoute({
    name: 'posts-inertia.index',
    url: '/posts-inertia',
    methods: ['get'] as const,
    component: 'Posts/Index',
}));

export type CreatePageProps = Inertia.SharedData;

export const create = annotatePageProps<CreatePageProps>()(defineRoute({
    name: 'posts-inertia.create',
    url: '/posts-inertia/create',
    methods: ['get'] as const,
    component: 'Posts/Create',
}));

export type StorePageProps = Inertia.SharedData & { post: string };

export const store = annotatePageProps<StorePageProps>()(defineRoute({
    name: 'posts-inertia.store',
    url: '/posts-inertia',
    methods: ['post'] as const,
    component: 'Posts/Show',
}));

export type ShowPageProps = Inertia.SharedData & { post: Post };

export const show = annotatePageProps<ShowPageProps>()(defineRoute({
    name: 'posts-inertia.show',
    url: '/posts-inertia/{post}',
    methods: ['get'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/Show',
}));

export type EditPageProps = Inertia.SharedData & { post: Post };

export const edit = annotatePageProps<EditPageProps>()(defineRoute({
    name: 'posts-inertia.edit',
    url: '/posts-inertia/{post}/edit',
    methods: ['get'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/Edit',
}));

export type UpdatePageProps = Inertia.SharedData & { post: Post };

export const update = annotatePageProps<UpdatePageProps>()(defineRoute({
    name: 'posts-inertia.update',
    url: '/posts-inertia/{post}',
    methods: ['put', 'patch'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
    component: 'Posts/Show',
}));

export const destroy = defineRoute({
    name: 'posts-inertia.destroy',
    url: '/posts-inertia/{post}',
    methods: ['delete'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

/**
 * Manages blog posts with inertia
 *
 * @see Workbench\App\Http\Controllers\PostInertiaController
 */
const PostInertiaController = {
    index,
    create,
    store,
    show,
    edit,
    update,
    destroy,
};

export default PostInertiaController;

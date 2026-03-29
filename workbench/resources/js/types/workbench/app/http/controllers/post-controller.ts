import { defineRoute } from '@tolki/ts/routes';

export const index = defineRoute({
    name: 'posts.index',
    url: '/posts',
    domain: null,
    methods: ['get'] as const,
});

export const show = defineRoute({
    name: 'posts.show',
    url: '/posts/{post}',
    domain: null,
    methods: ['get'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

export const store = defineRoute({
    name: 'posts.store',
    url: '/posts',
    domain: null,
    methods: ['post'] as const,
});

export const update = defineRoute({
    name: 'posts.update',
    url: '/posts/{post}',
    domain: null,
    methods: ['put'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

export const destroy = defineRoute({
    name: 'posts.destroy',
    url: '/posts/{post}',
    domain: null,
    methods: ['delete'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

/**
 * Manages blog posts
 *
 * @see Workbench\App\Http\Controllers\PostController
 */
const PostController = {
    index,
    show,
    store,
    update,
    destroy,
};

export default PostController;

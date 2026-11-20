import { defineRoute, annotateRequestPayload } from '@tolki/ts';

import type { StorePostRequest } from '../requests/store-post-request';
import type { UpdatePostRequest } from '../requests/update-post-request';

export const index = /* @__PURE__ */ defineRoute({
    name: 'posts.index',
    url: '/posts',
    methods: ['get', 'head'] as const,
});

export const show = /* @__PURE__ */ defineRoute({
    name: 'posts.show',
    url: '/posts/{post}',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

export const store = /* @__PURE__ */ annotateRequestPayload<StorePostRequest>()(/* @__PURE__ */ defineRoute({
    name: 'posts.store',
    url: '/posts',
    methods: ['post'] as const,
}));

export const update = /* @__PURE__ */ annotateRequestPayload<UpdatePostRequest>()(/* @__PURE__ */ defineRoute({
    name: 'posts.update',
    url: '/posts/{post}',
    methods: ['put'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
}));

export const destroy = /* @__PURE__ */ defineRoute({
    name: 'posts.destroy',
    url: '/posts/{post}',
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

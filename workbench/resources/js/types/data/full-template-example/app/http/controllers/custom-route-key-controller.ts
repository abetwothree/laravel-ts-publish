import { defineRoute } from '@tolki/ts';

export const show = /* @__PURE__ */ defineRoute({
    name: 'slug-posts.show',
    url: '/slug-posts/{slugPost}',
    methods: ['get', 'head'] as const,
    args: [{name: 'slugPost', required: true, _routeKey: 'slug'}] as const,
});

/** @see Workbench\App\Http\Controllers\CustomRouteKeyController */
const CustomRouteKeyController = {
    show,
};

export default CustomRouteKeyController;

import { defineRoute } from '@tolki/ts';

export const show = /* @__PURE__ */ defineRoute({
    name: 'attribute-route-key-posts.show',
    url: '/attribute-route-key-posts/{post}',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'slug'}] as const,
});

/** @see Workbench\App\Http\Controllers\AttributeRouteKeyController */
const AttributeRouteKeyController = {
    show,
};

export default AttributeRouteKeyController;

import { defineRoute } from '@tolki/ts';

export const show = /* @__PURE__ */ defineRoute({
    name: 'articles.show',
    url: '/articles/{article}',
    methods: ['get', 'head'] as const,
    args: [{name: 'article', required: true, _routeKey: 'slug'}] as const,
});

/** @see Workbench\App\Http\Controllers\CustomKeyController */
const CustomKeyController = {
    show,
};

export default CustomKeyController;

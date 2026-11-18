import { defineRoute } from '@tolki/ts';

export const index = /* @__PURE__ */ defineRoute({
    name: 'nested.index',
    url: '/nested',
    methods: ['get', 'head'] as const,
});

export const show = /* @__PURE__ */ defineRoute({
    name: 'nested.show',
    url: '/nested/{id}',
    methods: ['get', 'head'] as const,
    args: [{name: 'id', required: true}] as const,
});

/** @see Workbench\App\Http\Controllers\Nested\NestedController */
const NestedController = {
    index,
    show,
};

export default NestedController;

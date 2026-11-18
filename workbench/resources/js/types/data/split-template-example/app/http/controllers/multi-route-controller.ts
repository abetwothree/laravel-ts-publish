import { defineRoute } from '@tolki/ts';

export const action = /* @__PURE__ */ defineRoute({
    name: 'multi.action',
    url: '/multi-2',
    methods: ['get', 'head'] as const,
});

/** @see Workbench\App\Http\Controllers\MultiRouteController */
const MultiRouteController = {
    action,
};

export default MultiRouteController;

import { defineRoute } from '@tolki/ts';

export const byStatus = /* @__PURE__ */ defineRoute({
    name: 'posts.byStatus',
    url: '/posts/status/{status}',
    methods: ['get', 'head'] as const,
    args: [{name: 'status', required: true, _enumValues: [0, 1]}] as const,
});

/** @see Workbench\App\Http\Controllers\EnumBoundController */
const EnumBoundController = {
    byStatus,
};

export default EnumBoundController;

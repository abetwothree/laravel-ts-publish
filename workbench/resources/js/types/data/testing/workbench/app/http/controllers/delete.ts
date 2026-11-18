import { defineRoute } from '@tolki/ts';

export const index = /* @__PURE__ */ defineRoute({
    name: 'delete-items.index',
    url: '/delete-items',
    methods: ['get', 'head'] as const,
});

/** @see Workbench\App\Http\Controllers\Delete */
const Delete = {
    index,
};

export default Delete;

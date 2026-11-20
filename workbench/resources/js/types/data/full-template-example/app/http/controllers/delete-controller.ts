import { defineRoute } from '@tolki/ts';

export const deleteMethod = /* @__PURE__ */ defineRoute({
    name: 'items.delete',
    url: '/items/{id}',
    methods: ['delete'] as const,
    args: [{name: 'id', required: true}] as const,
});

export const exportMethod = /* @__PURE__ */ defineRoute({
    name: 'items.export',
    url: '/items/export',
    methods: ['get', 'head'] as const,
});

/** @see Workbench\App\Http\Controllers\DeleteController */
const DeleteController = {
    'delete': deleteMethod,
    'export': exportMethod,
};

export default DeleteController;

import { defineRoute } from '@tolki/ts/routes';

export const deleteMethod = defineRoute({
    name: 'items.delete',
    url: '/items/{id}',
    domain: null,
    methods: ['delete'] as const,
    args: [{name: 'id', required: true}] as const,
});

export const exportMethod = defineRoute({
    name: 'items.export',
    url: '/items/export',
    domain: null,
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\DeleteController
 */
const DeleteController = {
    'delete': deleteMethod,
    'export': exportMethod,
};

export default DeleteController;

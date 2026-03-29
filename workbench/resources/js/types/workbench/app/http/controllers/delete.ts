import { defineRoute } from '@tolki/ts/routes';

export const index = defineRoute({
    name: 'delete-items.index',
    url: '/delete-items',
    domain: null,
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\Delete
 */
const Delete = {
    index,
};

export default Delete;

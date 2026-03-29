import { defineRoute } from '@tolki/ts/routes';

export const index = defineRoute({
    name: 'nested.index',
    url: '/nested',
    domain: null,
    methods: ['get'] as const,
});

export const show = defineRoute({
    name: 'nested.show',
    url: '/nested/{id}',
    domain: null,
    methods: ['get'] as const,
    args: [{name: 'id', required: true}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\Nested\NestedController
 */
const NestedController = {
    index,
    show,
};

export default NestedController;

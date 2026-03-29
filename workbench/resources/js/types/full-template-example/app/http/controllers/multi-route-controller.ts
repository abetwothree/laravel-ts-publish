import { defineRoute } from '@tolki/ts/routes';

export const action = defineRoute({
    name: 'multi.action',
    url: '/multi-2',
    domain: null,
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\MultiRouteController
 */
const MultiRouteController = {
    action,
};

export default MultiRouteController;

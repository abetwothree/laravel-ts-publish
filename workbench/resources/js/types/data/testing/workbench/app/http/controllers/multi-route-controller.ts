import { defineRoute } from '@tolki/ts';

export const action = defineRoute({
    name: 'multi.action',
    url: '/multi-2',
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\MultiRouteController
 */
const MultiRouteController = {
    action,
};

export default MultiRouteController;

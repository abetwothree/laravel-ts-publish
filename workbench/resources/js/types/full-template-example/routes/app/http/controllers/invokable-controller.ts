import { defineRoute } from '@tolki/ts/routes';

export const __invoke = defineRoute({
    url: '/invokable',
    methods: ['get'] as const,
});

/**
 * @see Workbench\App\Http\Controllers\InvokableController
 */
const InvokableController = {
    __invoke,
};

export default InvokableController;

import { defineRoute } from '@tolki/ts/routes';

export const bound = defineRoute({
    name: 'invokable.model.bound',
    url: '/invokable-model-bound/{post}',
    domain: null,
    methods: ['get'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\InvokableModelBoundController
 */
const InvokableModelBoundController = {
    '__invoke': bound,
};

export default InvokableModelBoundController;

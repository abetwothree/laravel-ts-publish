import { defineRoute } from '@tolki/ts';

export const invoke = defineRoute({
    name: 'invokable.model.bound',
    url: '/invokable-model-bound/{post}',
    methods: ['get'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\InvokableModelBoundController
 */
const InvokableModelBoundController = {
    '__invoke': invoke,
};

export default InvokableModelBoundController;

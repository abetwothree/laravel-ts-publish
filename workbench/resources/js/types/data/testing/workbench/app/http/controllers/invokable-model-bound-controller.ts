import { defineRoute } from '@tolki/ts';

export const invoke = /* @__PURE__ */ defineRoute({
    name: 'invokable.model.bound',
    url: '/invokable-model-bound/{post}',
    methods: ['get', 'head'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

/** @see Workbench\App\Http\Controllers\InvokableModelBoundController */
const InvokableModelBoundController = invoke;

export default InvokableModelBoundController;

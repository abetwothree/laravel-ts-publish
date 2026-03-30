import { defineRoute } from '@tolki/ts/routes';

export const invoke = defineRoute({
    name: 'invokable.model.bound.plus',
    url: '/invokable-model-plus/{post}',
    domain: null,
    methods: ['get'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

export const extra = defineRoute({
    name: 'invokable.model.bound.extra',
    url: '/invokable-model-extra/{post}',
    domain: null,
    methods: ['post'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

export const surprise = defineRoute({
    name: 'invokable.model.bound.surprise',
    url: '/invokable-model-surprise/{post}',
    domain: null,
    methods: ['delete'] as const,
    args: [{name: 'post', required: true, _routeKey: 'id'}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\InvokableModelBoundPlusController
 */
const InvokableModelBoundPlusController = {
    '__invoke': invoke,
    extra,
    surprise,
};

export default InvokableModelBoundPlusController;

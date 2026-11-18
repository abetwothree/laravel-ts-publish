import { defineRoute } from '@tolki/ts';

export const show = /* @__PURE__ */ defineRoute({
    name: 'optional.show',
    url: '/optional/{param?}',
    methods: ['get', 'head'] as const,
    args: [{name: 'param', required: false}] as const,
});

export const multi = /* @__PURE__ */ defineRoute({
    name: 'optional.multi',
    url: '/optional/{one?}/{two?}',
    methods: ['get', 'head'] as const,
    args: [{name: 'one', required: false}, {name: 'two', required: false}] as const,
});

/** @see Workbench\App\Http\Controllers\OptionalParamController */
const OptionalParamController = {
    show,
    multi,
};

export default OptionalParamController;

import { defineRoute } from '@tolki/ts';

export const camel = /* @__PURE__ */ defineRoute({
    name: 'params.camel',
    url: '/params/{camelCase}/camel',
    methods: ['get', 'head'] as const,
    args: [{name: 'camelCase', required: true}] as const,
});

export const snake = /* @__PURE__ */ defineRoute({
    name: 'params.snake',
    url: '/params/{snake_case}/snake',
    methods: ['get', 'head'] as const,
    args: [{name: 'snake_case', required: true}] as const,
});

export const screaming = /* @__PURE__ */ defineRoute({
    name: 'params.screaming',
    url: '/params/{SCREAMING_SNAKE}/screaming',
    methods: ['get', 'head'] as const,
    args: [{name: 'SCREAMING_SNAKE', required: true}] as const,
});

/** @see Workbench\App\Http\Controllers\ParameterCaseController */
const ParameterCaseController = {
    camel,
    snake,
    screaming,
};

export default ParameterCaseController;

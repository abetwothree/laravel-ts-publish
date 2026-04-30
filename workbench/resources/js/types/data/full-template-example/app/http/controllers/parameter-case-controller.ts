import { defineRoute } from '@tolki/ts';

export const camel = defineRoute({
    name: 'params.camel',
    url: '/params/{camelCase}/camel',
    methods: ['get'] as const,
    args: [{name: 'camelCase', required: true}] as const,
});

export const snake = defineRoute({
    name: 'params.snake',
    url: '/params/{snake_case}/snake',
    methods: ['get'] as const,
    args: [{name: 'snake_case', required: true}] as const,
});

export const screaming = defineRoute({
    name: 'params.screaming',
    url: '/params/{SCREAMING_SNAKE}/screaming',
    methods: ['get'] as const,
    args: [{name: 'SCREAMING_SNAKE', required: true}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\ParameterCaseController
 */
const ParameterCaseController = {
    camel,
    snake,
    screaming,
};

export default ParameterCaseController;

import { defineRoute } from '@tolki/ts';

export const show = defineRoute({
    name: 'optional.show',
    url: '/optional/{param?}',
    methods: ['get'] as const,
    args: [{name: 'param', required: false}] as const,
});

export const multi = defineRoute({
    name: 'optional.multi',
    url: '/optional/{one?}/{two?}',
    methods: ['get'] as const,
    args: [{name: 'one', required: false}, {name: 'two', required: false}] as const,
});

/**
 * @see Workbench\App\Http\Controllers\OptionalParamController
 */
const OptionalParamController = {
    show,
    multi,
};

export default OptionalParamController;

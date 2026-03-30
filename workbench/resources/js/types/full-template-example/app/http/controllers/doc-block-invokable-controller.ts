import { defineRoute } from '@tolki/ts/routes';

/** Performs the invokable action. */
export const invokable = defineRoute({
    name: 'docblock.invokable',
    url: '/docblock-invokable',
    domain: null,
    methods: ['get'] as const,
});

/**
 * Controller-level description.
 *
 * @see Workbench\App\Http\Controllers\DocBlockInvokableController
 */
const DocBlockInvokableController = {
    '__invoke': invokable,
};

export default DocBlockInvokableController;
